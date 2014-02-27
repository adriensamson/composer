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
 * The RuleWatchGraph efficiently propagates decisions to other rules
 *
 * All rules generated for solving a SAT problem should be inserted into the
 * graph. When a decision on a literal is made, the graph can be used to
 * propagate the decision to all other rules involving the literal, leading to
 * other trivial decisions resulting from unit clauses.
 *
 * @author Nils Adermann <naderman@naderman.de>
 */
class RuleWatchGraph
{
    protected $watchChains = array();

    /**
     * Inserts a rule node into the appropriate chains within the graph
     *
     * The node is prepended to the watch chains for each of the two literals it
     * watches.
     *
     * Assertions are skipped because they only depend on a single package and
     * have no alternative literal that could be true, so there is no need to
     * watch changes in any literals.
     *
     * @param RuleWatchNode $node The rule node to be inserted into the graph
     */
    public function insert(RuleWatchNode $node)
    {
        if ($node->getRule()->isAssertion()) {
            return;
        }

        foreach (array($node->watch1, $node->watch2) as $literal) {
            if (!isset($this->watchChains[$literal->getPackageName()][$literal->toInt()])) {
                $this->watchChains[$literal->getPackageName()][$literal->toInt()] = new RuleWatchChain;
            }

            $this->watchChains[$literal->getPackageName()][$literal->toInt()]->unshift($node);
        }
    }

    /**
     * Propagates a decision on a literal to all rules watching the literal
     *
     * If a decision, e.g. +A has been made, then all rules containing -A, e.g.
     * (-A|+B|+C) now need to satisfy at least one of the other literals, so
     * that the rule as a whole becomes true, since with +A applied the rule
     * is now (false|+B|+C) so essentially (+B|+C).
     *
     * This means that all rules watching the literal -A need to be updated to
     * watch 2 other literals which can still be satisfied instead. So literals
     * that conflict with previously made decisions are not an option.
     *
     * Alternatively it can occur that a unit clause results: e.g. if in the
     * above example the rule was (-A|+B), then A turning true means that
     * B must now be decided true as well.
     *
     * @param Literal $decidedLiteral The literal which was decided (A in our example)
     * @param int $level          The level at which the decision took place and at which
     *     all resulting decisions should be made.
     * @param Decisions $decisions Used to check previous decisions and to
     *     register decisions resulting from propagation
     * @return Rule|null If a conflict is found the conflicting rule is returned
     */
    public function propagateLiteral(Literal $decidedLiteral, $level, $decisions)
    {
        foreach ($this->getWatchChains($decidedLiteral) as $literalInt => $chain) {
            $chain->rewind();
            while ($chain->valid()) {
                $node = $chain->current();
                $otherWatch = $node->getOtherWatch($literalInt);

                if (!$node->getRule()->isDisabled() && !$decisions->satisfy($otherWatch)) {
                    $ruleLiterals = $node->getRule()->getLiterals();

                    $alternativeLiterals = array_filter($ruleLiterals, function ($ruleLiteral) use ($literalInt, $otherWatch, $decisions) {
                        return $literalInt != $ruleLiteral->toInt() &&
                        $otherWatch != $ruleLiteral &&
                        !$decisions->conflict($ruleLiteral);
                    });

                    if ($alternativeLiterals) {
                        reset($alternativeLiterals);
                        $this->moveWatch($decidedLiteral->getPackageName(), $literalInt, current($alternativeLiterals), $node);
                        continue;
                    }

                    if ($decisions->conflict($otherWatch)) {
                        return $node->getRule();
                    }

                    $decisions->decide($otherWatch, $level, $node->getRule());
                }

                $chain->next();
            }
        }

        return null;
    }

    protected function getWatchChains(Literal $literal)
    {
        $chains = array();

        $oppositeLiteral = $literal->getOppositeLiteral();
        if (isset($this->watchChains[$oppositeLiteral->getPackageName()][$oppositeLiteral->toInt()])) {
            $chains[$oppositeLiteral->toInt()] = $this->watchChains[$oppositeLiteral->getPackageName()][$oppositeLiteral->toInt()];
        }
        if ($literal->isPositive() && isset($this->watchChains[$oppositeLiteral->getPackageName()])) {
            foreach ($this->watchChains[$oppositeLiteral->getPackageName()] as $literalInt => $chain) {
                if ($literalInt > 0 && $literalInt != $literal->toInt()) {
                    $chains[$literalInt] = $chain;
                }
            }
        }

        return $chains;
    }

    /**
     * Moves a rule node from one watch chain to another
     *
     * The rule node's watched literals are updated accordingly.
     *
     * @param $fromPackageName
     * @param $fromLiteralInt mixed A literal the node used to watch
     * @param $toLiteral mixed A literal the node should watch now
     * @param $node mixed The rule node to be moved
     */
    protected function moveWatch($fromPackageName, $fromLiteralInt, Literal $toLiteral, $node)
    {
        if (!isset($this->watchChains[$toLiteral->getPackageName()][$toLiteral->toInt()])) {
            $this->watchChains[$toLiteral->getPackageName()][$toLiteral->toInt()] = new RuleWatchChain;
        }

        $node->moveWatch($fromLiteralInt, $toLiteral);
        $this->watchChains[$fromPackageName][$fromLiteralInt]->remove();
        $this->watchChains[$toLiteral->getPackageName()][$toLiteral->toInt()]->unshift($node);
    }
}
