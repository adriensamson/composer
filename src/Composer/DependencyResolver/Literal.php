<?php

namespace Composer\DependencyResolver;


class Literal
{
    protected $packageId;
    protected $negative;

    public function __construct($literal)
    {
        $this->packageId = abs($literal);
        $this->negative  = $literal < 0;
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
    public function getPackageId()
    {
        return $this->packageId;
    }
}
