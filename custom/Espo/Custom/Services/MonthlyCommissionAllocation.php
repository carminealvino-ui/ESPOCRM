<?php
declare(strict_types=1);
namespace Espo\Custom\Services;
final class MonthlyCommissionAllocation
{
    /** Signed rows; floor plus largest remainders preserves the monthly total exactly. */
    public static function rows(array $events, int $target): array
    {
        $net=0; $rows=[];
        foreach($events as $e) {
            if (!isset($e['quoteId'],$e['grossCents'],$e['kind']) || !is_int($e['grossCents'])) throw new \RuntimeException('Movimento mensile non valido.');
            if (!$e['grossCents']) continue;
            $key='pm:'.$e['kind'].':'.$e['quoteId'];
            if (isset($rows[$key])) throw new \RuntimeException('Movimento mensile duplicato.');
            $rows[$key]=$e; $net+=$e['grossCents'];
        }
        // An explicit historical full recovery may still have a 100% target.
        $rate = $net < 0 && $target === $net ? 100 : 35;
        if ((int)round($net*$rate/100)!==$target) throw new \RuntimeException('Il dettaglio contratti non coincide con il saldo mensile.');
        $sum=0;$rank=[];
        foreach($rows as $key=>&$e) {
            $numerator=$e['grossCents']*$rate;
            $e['amountCents']=(int)floor($numerator/100);$e['rate']=$rate;
            $sum+=$e['amountCents'];$rank[]=['key'=>$key,'fraction'=>$numerator-$e['amountCents']*100];
        }unset($e);
        usort($rank,fn($a,$b)=>($b['fraction']<=>$a['fraction']) ?: strcmp($a['key'],$b['key']));
        $left=$target-$sum;
        if ($left<0 || $left>count($rank)) throw new \RuntimeException('Arrotondamento mensile non valido.');
        for($j=0;$j<$left;$j++)$rows[$rank[$j]['key']]['amountCents']++;
        return $rows;
    }
}
