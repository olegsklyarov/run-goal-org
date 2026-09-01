<?php

declare(strict_types=1);

namespace RunOrg\Domain;

use RunOrg\Exception\UserError;

final class MonthJournal
{
    /**
     * @param list<Workout> $workouts
     */
    public function __construct(
        private readonly YearMonth $period,
        private readonly ?float $targetKm = null,
        private readonly array $workouts = [],
    ) {
        if ($targetKm !== null && $targetKm <= 0.0) {
            throw new UserError('Цель target_km должна быть больше нуля');
        }

        foreach ($workouts as $workout) {
            $this->assertBelongs($workout);
        }
    }

    public function period(): YearMonth
    {
        return $this->period;
    }

    public function targetKm(): ?float
    {
        return $this->targetKm;
    }

    /**
     * @return list<Workout>
     */
    public function workouts(): array
    {
        return $this->workouts;
    }

    public function withWorkout(Workout $workout): self
    {
        $this->assertBelongs($workout);

        $workouts = $this->workouts;
        array_splice($workouts, $this->insertionIndex($workout), 0, [$workout]);

        return new self($this->period, $this->targetKm, $workouts);
    }

    public function totalKm(): float
    {
        $total = 0.0;
        foreach ($this->workouts as $workout) {
            $total += $workout->km();
        }

        return $total;
    }

    /**
     * @return array<int, float>
     */
    public function distancesByDay(): array
    {
        $distances = [];
        foreach ($this->workouts as $workout) {
            $day = (int) $workout->date()->format('j');
            $distances[$day] = ($distances[$day] ?? 0.0) + $workout->km();
        }

        return $distances;
    }

    private function assertBelongs(Workout $workout): void
    {
        if (!$this->period->contains($workout->date())) {
            throw new UserError(sprintf(
                'Дата %s не относится к %s',
                $workout->date()->format('Y-m-d'),
                $this->period
            ));
        }
    }

    /**
     * Upper bound in the date-sorted journal: first index with a later day,
     * or n to append. Same-day workouts stay in insertion order after existing ones.
     */
    private function insertionIndex(Workout $workout): int
    {
        $nums = [];
        foreach ($this->workouts as $existing) {
            $nums[] = (int) $existing->date()->format('j');
        }
        $n = count($nums);
        $target = (int) $workout->date()->format('j');

        $left = -1;
        $right = $n;

        assert($left === -1 || $nums[$left] <= $target);
        assert($right === $n || $target < $nums[$right]);

        while ($right - $left > 1) {
            $middle = intdiv($left + $right, 2);
            if ($nums[$middle] <= $target) {
                $left = $middle;
            } else {
                $right = $middle;
            }

            assert($left === -1 || $nums[$left] <= $target);
            assert($right === $n || $target < $nums[$right]);
        }

        assert($left === -1 || $nums[$left] <= $target);
        assert($right === $n || $target < $nums[$right]);

        return $right;
    }
}
