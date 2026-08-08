;;; running-chart.el --- Build the running progress chart  -*- lexical-binding: t; -*-

;;; Commentary:

;; This file is loaded by the Local Variables block in a monthly journal
;; under YEAR/MM-month.org (for example 2026/08-август.org).
;; Open that journal and run `M-x running-update-chart' to rebuild the
;; generated data file and burn-up chart next to it.

;;; Code:

(require 'calendar)
(require 'cl-lib)
(require 'org)
(require 'org-table)
(require 'subr-x)

(defconst running-chart--directory
  (file-name-directory (or load-file-name buffer-file-name))
  "Directory containing the portable running journal.")

(defconst running-chart--weekday-names
  ["Вс" "Пн" "Вт" "Ср" "Чт" "Пт" "Сб"]
  "Russian abbreviated weekday names, starting with Sunday.")

(defconst running-chart--month-names
  ["январь" "февраль" "март" "апрель" "май" "июнь"
   "июль" "август" "сентябрь" "октябрь" "ноябрь" "декабрь"]
  "Russian month names used in chart titles.")

(defun running-chart--source-file ()
  "Return the absolute path of the monthly journal in the current buffer."
  (unless (and buffer-file-name
               (string-match-p "\\.org\\'" buffer-file-name))
    (user-error "Откройте месячный журнал (*.org) и повторите команду"))
  (expand-file-name buffer-file-name))

(defun running-chart--artifact-paths (source-file)
  "Return (DATA-PATH OUTPUT-PATH) for monthly journal SOURCE-FILE."
  (let* ((directory (file-name-directory source-file))
         (stem (file-name-base source-file)))
    (list (expand-file-name (format "%s-data.org" stem) directory)
          (expand-file-name (format "%s.png" stem) directory))))

(defun running-chart--table (name)
  "Read the Org table named NAME from the current buffer."
  (save-excursion
    (goto-char (point-min))
    (unless (re-search-forward
             (format "^[ \t]*#\\+name:[ \t]*%s[ \t]*$"
                     (regexp-quote name))
             nil t)
      (user-error "Не найдена таблица %s" name))
    (forward-line 1)
    (while (and (not (eobp))
                (looking-at-p "^[ \t]*$"))
      (forward-line 1))
    (unless (org-at-table-p)
      (user-error "После #+name: %s нет Org-таблицы" name))
    (org-table-to-lisp)))

(defun running-chart--data-rows (table name &optional allow-empty)
  "Return data rows from TABLE.
Signal an error for an empty NAME table unless ALLOW-EMPTY is non-nil."
  (let ((rows (cl-remove-if (lambda (row)
                              (or (eq row 'hline)
                                  (equal row (car table))))
                            table)))
    (unless (or rows allow-empty)
      (user-error "Таблица %s не содержит данных" name))
    rows))

(defun running-chart--number (value description)
  "Parse VALUE as a non-negative number described by DESCRIPTION."
  (let ((text (string-trim (format "%s" value))))
    (unless (string-match-p "\\`[0-9]+\\(?:\\.[0-9]+\\)?\\'" text)
      (user-error "%s должно быть неотрицательным числом: %s"
                  description text))
    (string-to-number text)))

(defun running-chart--config (table)
  "Validate TABLE and return (MONTH-STRING YEAR MONTH TARGET-KM)."
  (let ((rows (running-chart--data-rows table "running-config")))
    (unless (= (length rows) 1)
      (user-error "В running-config должна быть ровно одна строка данных"))
    (pcase-let* ((`(,month-string ,target-value . ,_) (car rows))
                 (target-km
                  (running-chart--number target-value "Цель target_km")))
      (unless (and (stringp month-string)
                   (string-match
                    "\\`\\([0-9]\\{4\\}\\)-\\([0-9]\\{2\\}\\)\\'"
                    month-string))
        (user-error "Месяц должен иметь формат YYYY-MM: %s" month-string))
      (let ((year (string-to-number (match-string 1 month-string)))
            (month (string-to-number (match-string 2 month-string))))
        (unless (<= 1 month 12)
          (user-error "Некорректный номер месяца: %s" month-string))
        (unless (> target-km 0)
          (user-error "Цель target_km должна быть больше нуля"))
        (list month-string year month target-km)))))

(defun running-chart--parse-date (value)
  "Parse and validate ISO date VALUE, returning (YEAR MONTH DAY)."
  (let ((text (string-trim (format "%s" value))))
    (unless (string-match
             "\\`\\([0-9]\\{4\\}\\)-\\([0-9]\\{2\\}\\)-\\([0-9]\\{2\\}\\)\\'"
             text)
      (user-error "Дата должна иметь формат YYYY-MM-DD: %s" text))
    (let ((year (string-to-number (match-string 1 text)))
          (month (string-to-number (match-string 2 text)))
          (day (string-to-number (match-string 3 text))))
      (unless (and (<= 1 month 12)
                   (<= 1 day (calendar-last-day-of-month month year)))
        (user-error "Некорректная дата: %s" text))
      (list year month day))))

(defun running-chart--distances-by-day (table selected-year selected-month)
  "Aggregate workout TABLE for SELECTED-YEAR and SELECTED-MONTH."
  (let ((distances (make-hash-table :test #'eql)))
    (dolist (row (running-chart--data-rows table "running-log" t))
      (pcase-let* ((`(,date-value ,distance-value . ,_) row)
                   (`(,year ,month ,day)
                    (running-chart--parse-date date-value))
                   (distance
                    (running-chart--number distance-value
                                           "Дистанция тренировки")))
        (when (and (= year selected-year)
                   (= month selected-month))
          (puthash day
                   (+ distance (gethash day distances 0.0))
                   distances))))
    distances))

(defun running-chart--max-logged-day (distances)
  "Return the largest day key in DISTANCES, or 0 if empty."
  (let ((max-day 0))
    (maphash (lambda (day _distance)
               (when (> day max-day)
                 (setq max-day day)))
             distances)
    max-day))

(defun running-chart--rows (year month target-km distances)
  "Build chart rows for YEAR, MONTH, TARGET-KM, and DISTANCES."
  (let* ((days-in-month (calendar-last-day-of-month month year))
         (last-actual-day (running-chart--max-logged-day distances))
         (cumulative 0.0)
         rows)
    (cl-loop for day from 1 to days-in-month do
             (let* ((weekday-index
                     (calendar-day-of-week (list month day year)))
                    (weekday
                     (aref running-chart--weekday-names weekday-index))
                    (label
                     (if (= (% day 2) 1)
                         (format "%s\\n%d" weekday day)
                       weekday))
                    (ideal
                     (* target-km (/ (float day) days-in-month)))
                    actual)
               (when (<= day last-actual-day)
                 (setq cumulative
                       (+ cumulative (gethash day distances 0.0)))
                 (setq actual (format "%.2f" cumulative)))
               (push
                (list day label (format "%.2f" ideal) (or actual "NaN"))
                rows)))
    (nreverse rows)))

(defun running-chart--write-data-file (path rows)
  "Atomically write generated Org table ROWS to PATH."
  (let ((temporary-path
         (make-temp-file
          (expand-file-name ".running-data-" running-chart--directory))))
    (unwind-protect
        (progn
          (with-temp-file temporary-path
            (insert "#+title: Подготовленные данные для графика бега\n")
            (insert "# Этот файл создан командой running-update-chart.\n\n")
            (insert "#+name: running-chart-data\n")
            (insert "| day | label | ideal | actual |\n")
            (insert "|-----+-------+-------+--------|\n")
            (dolist (row rows)
              (insert (format "| %2d | %-5s | %6s | %6s |\n"
                              (nth 0 row)
                              (nth 1 row)
                              (nth 2 row)
                              (nth 3 row)))))
          (set-file-modes temporary-path #o644)
          (rename-file temporary-path path t))
      (when (file-exists-p temporary-path)
        (delete-file temporary-path)))))

(defun running-chart--write-plot-data (path rows)
  "Write tab-separated chart ROWS to PATH."
  (with-temp-file path
    (dolist (row rows)
      (insert (mapconcat (lambda (value) (format "%s" value)) row "\t"))
      (insert "\n"))))

(defun running-chart--y-max (target-km rows)
  "Return Y-axis max from TARGET-KM and actual values in ROWS.
The result is the max of TARGET-KM and the largest actual cumulative
distance, rounded up to the nearest multiple of 5, plus one extra
tick of headroom so markers at the peak are not clipped."
  (let ((peak target-km))
    (dolist (row rows)
      (let ((actual (nth 3 row)))
        (unless (or (null actual) (equal actual "NaN"))
          (setq peak (max peak (string-to-number actual))))))
    (+ 5 (* 5 (ceiling (/ (float peak) 5))))))

(defun running-chart--call-gnuplot
    (rows output-path title target-km days-in-month)
  "Render ROWS to OUTPUT-PATH with TITLE and chart bounds."
  (let ((gnuplot (executable-find "gnuplot"))
        (script-path
         (expand-file-name "running-chart.gnuplot" running-chart--directory))
        (data-path
         (make-temp-file
          (expand-file-name ".running-plot-" running-chart--directory)
          nil ".tsv"))
        (temporary-output
         (make-temp-file
          (expand-file-name ".running-chart-" running-chart--directory)
          nil ".png"))
        (log-buffer (generate-new-buffer " *running-gnuplot*"))
        (y-max (running-chart--y-max target-km rows)))
    (unwind-protect
        (progn
          (unless gnuplot
            (user-error "Не найден gnuplot в PATH"))
          (unless (file-readable-p script-path)
            (user-error "Не найден файл %s" script-path))
          (running-chart--write-plot-data data-path rows)
          (let ((status
                 (call-process
                  gnuplot nil log-buffer nil
                  "-c" script-path data-path temporary-output title
                  (number-to-string target-km)
                  (number-to-string y-max)
                  (number-to-string days-in-month))))
            (unless (and (integerp status) (zerop status))
              (user-error "gnuplot завершился с ошибкой: %s"
                          (string-trim
                           (with-current-buffer log-buffer
                             (buffer-string))))))
          (set-file-modes temporary-output #o644)
          (rename-file temporary-output output-path t))
      (dolist (path (list data-path temporary-output))
        (when (file-exists-p path)
          (delete-file path)))
      (kill-buffer log-buffer))))

(defun running-chart--refresh-inline-image (source-file)
  "Refresh inline images in the buffer visiting SOURCE-FILE."
  (when-let ((buffer (find-buffer-visiting source-file)))
    (with-current-buffer buffer
      (when (derived-mode-p 'org-mode)
        (org-redisplay-inline-images)))))

;;;###autoload
(defun running-update-chart ()
  "Rebuild the data file and burn-up chart for the current monthly journal."
  (interactive)
  (let* ((source-file (running-chart--source-file))
         config-table
         workout-table)
    (unless (file-readable-p source-file)
      (user-error "Не найден журнал %s" source-file))
    (with-current-buffer (find-file-noselect source-file)
      (setq config-table (running-chart--table "running-config")
            workout-table (running-chart--table "running-log")))
    (pcase-let* ((`(,_month-string ,year ,month ,target-km)
                  (running-chart--config config-table))
                 (distances
                  (running-chart--distances-by-day
                   workout-table year month))
                 (rows
                  (running-chart--rows year month target-km distances))
                 (days-in-month (length rows))
                 (`(,data-path ,output-path)
                  (running-chart--artifact-paths source-file))
                 (title
                  (format "Бег: %s %d"
                          (aref running-chart--month-names (1- month))
                          year)))
      (running-chart--write-data-file data-path rows)
      (running-chart--call-gnuplot
       rows output-path title target-km days-in-month)
      (running-chart--refresh-inline-image source-file)
      (message "График обновлён: %s" output-path))))

(provide 'running-chart)

;;; running-chart.el ends here
