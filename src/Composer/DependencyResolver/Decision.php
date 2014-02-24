<?php

namespace Composer\DependencyResolver;


class Decision
{
    protected $level;
    protected $negative;

    public function __construct($level, $negative)
    {
        $this->level    = $level;
        $this->negative = $negative;
    }

    /**
     * @return bool
     */
    public function isNegative()
    {
        return $this->negative;
    }

    /**
     * @return bool
     */
    public function isPositive()
    {
        return !$this->negative;
    }

    /**
     * @return int
     */
    public function getLevel()
    {
        return $this->level;
    }
} 