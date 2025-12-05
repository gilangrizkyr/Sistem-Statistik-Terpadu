<?php

namespace App\Libraries;

class FilterBuilder
{
    public function build(array $filters): array
    {
        $conditions = [];

        if (isset($filters['quarter']) && $filters['quarter'] !== 'all') {
            $conditions['quarter'] = $filters['quarter'];
        }

        if (isset($filters['year']) && $filters['year'] !== 'all') {
            $conditions['year'] = $filters['year'];
        }

        return $conditions;
    }
}