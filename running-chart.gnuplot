# Arguments:
# ARG1=data file, ARG2=output SVG, ARG3=title, ARG4=target km (0 = no ideal line),
# ARG5=y-axis max, ARG6=number of x points (days or months),
# ARG7=x-axis label, ARG8=y-axis tick step.
#
# TSV columns: 1=index, 2=label, 3=sunday (0/1), 4=actual (NaN if unknown),
# 5=marker (actual on workout days, otherwise NaN).

set encoding utf8
# A4 landscape: 297mm × 210mm at 96 dpi (CSS px), so print fills the sheet.
set terminal svg size 1123,794 enhanced font "Arial,12" background rgb "white"
set output ARG2
set size 1,1
set origin 0,0

set title ARG3
set xlabel ARG7 offset 0,-0.5
set ylabel "Накопленный километраж, км"

set key top left
set grid ytics
set border 3
set tics out
set lmargin 10
set rmargin 2
set tmargin 3
set bmargin 5
set datafile separator "\t"
set datafile missing "NaN"

target_km = real(ARG4)
y_max = real(ARG5)
n_points = int(ARG6)
y_step = real(ARG8)

set xrange [0 : n_points + 0.5]
set yrange [0 : y_max]
set ytics y_step
set format y "%.0f"

# Ideal plan: straight line from (0, 0) to (n_points, target_km).
# Omitted when ARG4 is 0 (month without target_km).
ideal(x) = target_km * x / n_points

# Actual: line through every visible day/month; points only on logged workouts.
if (target_km > 0) {
    plot \
        ARG1 using 1:(y_max):xticlabels(2) with impulses linewidth 1 dashtype 3 linecolor rgb "#999999" notitle, \
        ARG1 using 1:(int(column(3)) ? y_max : NaN) with impulses linewidth 1 dashtype 1 linecolor rgb "#999999" notitle, \
        [0:n_points] ideal(x) with lines linewidth 1.5 dashtype 2 linecolor rgb "#999999" title "Идеальный план", \
        ARG1 using 1:4 with lines linewidth 3 linecolor rgb "#2374d8" title "Фактический результат", \
        ARG1 using 1:5 with points pointtype 7 pointsize 0.9 linecolor rgb "#2374d8" notitle
} else {
    plot \
        ARG1 using 1:(y_max):xticlabels(2) with impulses linewidth 1 dashtype 3 linecolor rgb "#999999" notitle, \
        ARG1 using 1:(int(column(3)) ? y_max : NaN) with impulses linewidth 1 dashtype 1 linecolor rgb "#999999" notitle, \
        ARG1 using 1:4 with lines linewidth 3 linecolor rgb "#2374d8" title "Фактический результат", \
        ARG1 using 1:5 with points pointtype 7 pointsize 0.9 linecolor rgb "#2374d8" notitle
}
