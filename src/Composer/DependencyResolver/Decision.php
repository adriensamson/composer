<?php

namespace Composer\DependencyResolver;


class Decision
{
    protected $level;
    protected $literalOrNegative;

    public function __construct($level, $literalOrNegative)
    {
        $this->level             = $level;
        $this->literalOrNegative = $literalOrNegative;
    }

    /**
     * @return bool
     */
    public function isNegative()
    {
        return $this->literalOrNegative === false;
    }

    /**
     * @param Literal $literal
     * @return bool
     */
    public function isLiteral(Literal $literal)
    {
        return $this->literalOrNegative == $literal;
    }

    /**
     * @return int
     */
    public function getLevel()
    {
        return $this->level;
    }

    public function getLiteral()
    {
        return $this->literalOrNegative;
    }
} 