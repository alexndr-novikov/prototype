<?php

namespace Spiral\Prototype\ClassDefinition\ConflictResolver;

class Sequences
{
    /**
     * Examples:
     * [], <any> => 0
     *
     *
     * @param array $sequences
     * @param int   $originSequence
     *
     * @return int
     */
    public function find(array $sequences, int $originSequence): int
    {
        if (empty($sequences) || $originSequence > max($sequences)) {
            return $originSequence;
        }

        $gaps = $this->skippedSequences($sequences);

        if (isset($gaps[$originSequence])) {
            return $originSequence;
        }

        //we do not add "1" as postfix: $var, $var2, $var3, etc
        unset($gaps[1]);
        if (empty($gaps)) {
            $max = max($sequences);
            if ($max === 0) {
                return 2;
            }

            return $max + 1;
        }

        return min($gaps);
    }

    private function skippedSequences(array $sequences): array
    {
        $skipped = [];
        for ($i = 0; $i < max($sequences); $i++) {
            if (!in_array($i, $sequences)) {
                $skipped[$i] = $i;
            }
        }

        return $skipped;
    }
}