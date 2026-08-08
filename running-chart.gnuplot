# Arguments:
# ARG1=data file, ARG2=output PNG, ARG3=title, ARG4=target km,
# ARG5=y-axis max, ARG6=number of days in the month.

set encoding utf8
set terminal pngcairo size 1400,750 enhanced font "Arial,12"
set output ARG2

set title ARG3
set xlabel "День месяца" offset 0,-1
set ylabel "Накопленный километраж, км"

set key top left
set grid ytics
set border 3
set tics out
set bmargin 5
set datafile separator "\t"
set datafile missing "NaN"

target_km = real(ARG4)
y_max = real(ARG5)
days_in_month = int(ARG6)

set xrange [0 : days_in_month + 0.5]
set yrange [0 : y_max]
set ytics 5
set format y "%.0f"

# Ideal plan: straight line from (0, 0) to (days_in_month, target_km).
ideal(x) = target_km * x / days_in_month

# Vertical guides: every day (current style), Sundays thicker.
plot \
    ARG1 using 1:(y_max):xticlabels(2) with impulses linewidth 1 dashtype 3 linecolor rgb "#999999" notitle, \
    ARG1 using 1:(strstrt(strcol(2), "Вс") ? y_max : NaN) with impulses linewidth 1 dashtype 1 linecolor rgb "#999999" notitle, \
    [0:days_in_month] ideal(x) with lines linewidth 1.5 dashtype 2 linecolor rgb "#999999" title "Идеальный план", \
    ARG1 using 1:4 with linespoints linewidth 3 pointtype 7 pointsize 0.9 linecolor rgb "#2374d8" title "Фактический результат"
