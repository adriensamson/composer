<?php

namespace Composer\DependencyResolver;


use Composer\Package\AliasPackage;
use Composer\Package\PackageInterface;

class Literal
{
    protected $packageId;
    protected $packageName;
    protected $negative;
    protected $aliasOf;

    public function __construct($packageName, $literal, $aliasOf = null)
    {
        $this->packageName = $packageName;
        $this->packageId   = abs($literal);
        $this->negative    = $literal < 0;
        $this->aliasOf     = $aliasOf;
    }

    public static function createPositiveFromPackage(PackageInterface $package)
    {
        return new self($package->getName(), $package->getId(), $package instanceof AliasPackage ? $package->getAliasOf()->getId() : null);
    }

    public static function createNegativeFromPackage(PackageInterface $package)
    {
        return new self($package->getName(), -$package->getId(), $package instanceof AliasPackage ? $package->getAliasOf()->getId() : null);
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
        return $this->packageName . ($this->aliasOf ? '/'.$this->aliasOf : '');
    }

    public function getAliasOf()
    {
        return $this->aliasOf;
    }

    /**
     * @return int
     */
    public function toInt()
    {
        return ($this->negative ? -1 : 1) * $this->packageId;
    }
}
