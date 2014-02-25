<?php

namespace Composer\DependencyResolver;


class Literal
{
    protected $packageId;
    protected $packageName;
    protected $negative;

    public function __construct($packageName, $literal)
    {
        $this->packageName = $packageName;
        $this->packageId   = abs($literal);
        $this->negative    = $literal < 0;
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

    /**
     * @return string
     */
    public function getPackageName()
    {
        return $this->packageName;
    }

    /**
     * @return int
     */
    public function toInt()
    {
        return ($this->negative ? -1 : 1) * $this->packageId;
    }
}
