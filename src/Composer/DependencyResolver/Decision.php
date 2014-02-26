<?php

namespace Composer\DependencyResolver;


class Decision
{
    protected $level;
    protected $packageId;
    protected $negativePackageIds;

    public function __construct($level, $packageId = null, array $negativePackageIds = array())
    {
        $this->level              = $level;
        $this->packageId          = $packageId;
        $this->negativePackageIds = $negativePackageIds;
    }

    /**
     * @param Literal $literal
     * @return bool
     */
    public function isNegative(Literal $literal)
    {
        if (null !== $this->packageId) {
            return $this->packageId != $literal->getPackageId();
        }

        return in_array($literal->getPackageId(), $this->negativePackageIds);
    }

    /**
     * @param Literal $literal
     * @return bool
     */
    public function isPositive(Literal $literal)
    {
        return $this->packageId == $literal->getPackageId();
    }

    /**
     * @return int
     */
    public function getLevel()
    {
        return $this->level;
    }

    public function getPackageId()
    {
        return $this->packageId;
    }

    public function getNegativePackageIds()
    {
        return $this->negativePackageIds;
    }
} 