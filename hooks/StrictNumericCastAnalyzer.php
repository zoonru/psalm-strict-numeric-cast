<?php

declare(strict_types=1);

namespace Orklah\PsalmStrictNumericCast\Hooks;

use PhpParser\Node\Expr\Cast\Int_;
use PhpParser\Node\Expr\Cast\Double;
use Psalm\CodeLocation;
use Psalm\Issue\PluginIssue;
use Psalm\IssueBuffer;
use Psalm\Plugin\EventHandler\AfterExpressionAnalysisInterface;
use Psalm\Plugin\EventHandler\Event\AfterExpressionAnalysisEvent;
use Psalm\Type;
use Psalm\Type\Atomic\TLiteralString;

class StrictNumericCastAnalyzer implements AfterExpressionAnalysisInterface
{
    public static function afterExpressionAnalysis(
        AfterExpressionAnalysisEvent $event
    ): ?bool
    {
        $expr = $event->getExpr();
        $statements_source = $event->getStatementsSource();
        if(!$expr instanceof Int_ && !$expr instanceof Double){
            return true;
        }

        $previous_union = $statements_source->getNodeTypeProvider()->getType($expr->expr);

        if($previous_union === null){
            return true;
        }

        $eligible_type = null;
        foreach($previous_union->getAtomicTypes() as $previous_type) {
            if ($previous_type instanceof Type\Atomic\TNumericString) {
                //everything is good!
                continue;
            } elseif (
                $previous_type instanceof TLiteralString &&
                preg_match('#\d#', $previous_type->value[0] ?? '') // will probably have to inverse the check to forbid chars instead
            ) {
                //this is good too. It's not a numeric-string but this is actually more precise
                continue;
            } elseif (!$previous_type instanceof Type\Atomic\TString) {
                //nothing to see here, it's not a string
                continue;
            } else {
                $eligible_type = $previous_type;
            }
        }

        if($eligible_type === null){
            // we didn't found any standard string.
            return true;
        }

        //We're at the end! We should have found a non-numeric, non-literal numeric string

        if (IssueBuffer::accepts(
            new StrictNumericCast(
                'Unsafe cast from numeric to string. Consider documenting the string as numeric-string.',
                new CodeLocation($statements_source, $expr)
            ),
            $statements_source->getSuppressedIssues()
        )
        ) {
            // continue
        }

        return true;
    }
}

class StrictNumericCast extends PluginIssue
{
}
