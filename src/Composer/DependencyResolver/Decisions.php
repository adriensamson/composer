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
    protected $previousDecisions;
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
        $packageName = $literal->getPackageName();

        return (
            $literal->isPositive() && isset($this->decisionMap[$packageName]) && $this->decisionMap[$packageName]->isPositive($literal) ||
            $literal->isNegative() && isset($this->decisionMap[$packageName]) && $this->decisionMap[$packageName]->isNegative($literal)
        );
    }

    public function conflict(Literal $literal)
    {
        $packageName = $literal->getPackageName();

        return (
            (isset($this->decisionMap[$packageName]) && $this->decisionMap[$packageName]->isPositive($literal) && $literal->isNegative()) ||
            (isset($this->decisionMap[$packageName]) && $this->decisionMap[$packageName]->isNegative($literal) && $literal->isPositive())
        );
    }

    public function decided(Literal $literal)
    {
        if (!isset($this->decisionMap[$literal->getPackageName()])) {
            return false;
        }
        $decision = $this->decisionMap[$literal->getPackageName()];

        return $decision->isPositive($literal) || $decision->isNegative($literal);
    }

    public function undecided(Literal $literal)
    {
        return !$this->decided($literal);
    }

    public function decidedInstall(Literal $literal)
    {
        $packageName = $literal->getPackageName();

        return isset($this->decisionMap[$packageName]) && $this->decisionMap[$packageName]->isPositive($literal);
    }

    public function decisionLevel(Literal $literal)
    {
        if ($this->decided($literal)) {
            return $this->decisionMap[$literal->getPackageName()]->getLevel();
        }

        return 0;
    }

    public function decisionRule(Literal $literal)
    {
        $packageName = $literal->getPackageName();
        foreach ($this->decisionQueue as $i => $decision) {
            if ($packageName === $decision[self::DECISION_LITERAL]->getPackageName()) {
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
            $this->revertLiteral($decision[self::DECISION_LITERAL]);
        }
    }

    public function resetToOffset($offset)
    {
        while (count($this->decisionQueue) > $offset + 1) {
            $decision = array_pop($this->decisionQueue);
            $this->revertLiteral($decision[self::DECISION_LITERAL]);
        }
    }

    public function revertLast()
    {
        $this->revertLiteral($this->lastLiteral());
        array_pop($this->decisionQueue);
    }

    protected function revertLiteral(Literal $literal)
    {
        $level = $this->decisionMap[$literal->getPackageName()]->getLevel();
        if (isset($this->previousDecisions[$literal->getPackageName()][$level])) {
            $this->decisionMap[$literal->getPackageName()] = $this->previousDecisions[$literal->getPackageName()][$level];
            unset($this->previousDecisions[$literal->getPackageName()][$level]);
        } else {
            unset($this->decisionMap[$literal->getPackageName()]);
        }
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
        $packageName = $literal->getPackageName();

        $previousDecision = isset($this->decisionMap[$packageName]) ? $this->decisionMap[$packageName] : null;
        $previousNegatives = array();
        if ($previousDecision !== null && $previousDecision->getLevel() != 0) {
            if ($previousDecision->getPackageId() === null) {
                $previousNegatives = $previousDecision->getNegativePackageIds();
            } else {
                $literalString = $this->pool->literalToString($literal);
                $package = $this->pool->literalToPackage($literal);
                $previousLiteralString = $this->pool->literalToString($previousDecision->getPackageId());
                throw new SolverBugException(
                    "Trying to decide $literalString on level $level, even though $package was previously decided as ".$previousLiteralString."."
                );
            }
        }

        $this->previousDecisions[$packageName][$level] = $previousDecision;
        if ($literal->isPositive()) {
            $this->decisionMap[$packageName] = new Decision($level, $literal->getPackageId());
        } else {
            $this->decisionMap[$packageName] = new Decision($level, null, array_merge($previousNegatives, array($literal->getPackageId())));
        }
    }
}
