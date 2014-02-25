<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer\DependencyResolver;

/**
 * Stores decisions on installing, removing or keeping packages
 *
 * @author Nils Adermann <naderman@naderman.de>
 */
class Decisions implements \Iterator, \Countable
{
    const DECISION_LITERAL = 0;
    const DECISION_REASON = 1;

    /**
     * @var Pool
     */
    protected $pool;
    /**
     * @var Decision[]
     */
    protected $decisionMap;
    protected $decisionQueue = array();

    public function __construct($pool)
    {
        $this->pool = $pool;
        $this->decisionMap = array();
    }

    public function decide(Literal $literal, $level, $why)
    {
        $this->addDecision($literal, $level);
        $this->decisionQueue[] = array(
            self::DECISION_LITERAL => $literal,
            self::DECISION_REASON => $why,
        );
    }

    public function satisfy(Literal $literal)
    {
        $packageId = $literal->getPackageId();

        return (
            $literal->isPositive() && isset($this->decisionMap[$packageId]) && $this->decisionMap[$packageId]->isPositive() ||
            $literal->isNegative() && isset($this->decisionMap[$packageId]) && $this->decisionMap[$packageId]->isNegative()
        );
    }

    public function conflict(Literal $literal)
    {
        $packageId = $literal->getPackageId();

        return (
            (isset($this->decisionMap[$packageId]) && $this->decisionMap[$packageId]->isPositive() && $literal->isNegative()) ||
            (isset($this->decisionMap[$packageId]) && $this->decisionMap[$packageId]->isNegative() && $literal->isPositive())
        );
    }

    public function decided(Literal $literal)
    {
        return !empty($this->decisionMap[$literal->getPackageId()]);
    }

    public function undecided(Literal $literal)
    {
        return empty($this->decisionMap[$literal->getPackageId()]);
    }

    public function decidedInstall(Literal $literal)
    {
        $packageId = $literal->getPackageId();

        return isset($this->decisionMap[$packageId]) && $this->decisionMap[$packageId]->isPositive();
    }

    public function decisionLevel(Literal $literal)
    {
        $packageId = $literal->getPackageId();
        if (isset($this->decisionMap[$packageId])) {
            return $this->decisionMap[$packageId]->getLevel();
        }

        return 0;
    }

    public function decisionRule(Literal $literal)
    {
        $packageId = $literal->getPackageId();
        foreach ($this->decisionQueue as $i => $decision) {
            if ($packageId === $decision[self::DECISION_LITERAL]->getPackageId()) {
                return $decision[self::DECISION_REASON];
            }
        }

        return null;
    }

    public function atOffset($queueOffset)
    {
        return $this->decisionQueue[$queueOffset];
    }

    public function validOffset($queueOffset)
    {
        return $queueOffset >= 0 && $queueOffset < count($this->decisionQueue);
    }

    public function lastReason()
    {
        return $this->decisionQueue[count($this->decisionQueue) - 1][self::DECISION_REASON];
    }

    public function lastLiteral()
    {
        return $this->decisionQueue[count($this->decisionQueue) - 1][self::DECISION_LITERAL];
    }

    public function reset()
    {
        while ($decision = array_pop($this->decisionQueue)) {
            unset($this->decisionMap[$decision[self::DECISION_LITERAL]->getPackageId()]);
        }
    }

    public function resetToOffset($offset)
    {
        while (count($this->decisionQueue) > $offset + 1) {
            $decision = array_pop($this->decisionQueue);
            unset($this->decisionMap[$decision[self::DECISION_LITERAL]->getPackageId()]);
        }
    }

    public function revertLast()
    {
        unset($this->decisionMap[$this->lastLiteral()->getPackageId()]);
        array_pop($this->decisionQueue);
    }

    public function count()
    {
        return count($this->decisionQueue);
    }

    public function rewind()
    {
        end($this->decisionQueue);
    }

    public function current()
    {
        return current($this->decisionQueue);
    }

    public function key()
    {
        return key($this->decisionQueue);
    }

    public function next()
    {
        return prev($this->decisionQueue);
    }

    public function valid()
    {
        return false !== current($this->decisionQueue);
    }

    public function isEmpty()
    {
        return count($this->decisionQueue) === 0;
    }

    protected function addDecision(Literal $literal, $level)
    {
        $packageId = $literal->getPackageId();

        $previousDecision = isset($this->decisionMap[$packageId]) ? $this->decisionMap[$packageId] : null;
        if ($previousDecision !== null && $previousDecision->getLevel() != 0) {
            $literalString = $this->pool->literalToString($literal);
            $package = $this->pool->literalToPackage($literal);
            throw new SolverBugException(
                "Trying to decide $literalString on level $level, even though $package was previously decided as ".(int) $previousDecision."."
            );
        }

        if ($literal->isPositive()) {
            $this->decisionMap[$packageId] = new Decision($level, false);
        } else {
            $this->decisionMap[$packageId] = new Decision($level, true);
        }
    }
}
