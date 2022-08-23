<?php



namespace Ensemble;
use Ensemble\Schedule\Schedule;
use Ensemble\Schedule\DailyScheduler;

$s = new Schedule();
$s->setTimezone('UTC');
$s->setPoint(0, 0);
$s->setPeriod('00:00', '00:30', 1);
$s->setPeriod('12:00', '13:30', 2);
echo $s->prettyPrint(true);


$s = new Schedule();
$s->setTimezone('Europe/London');
$s->setPoint(0, 0);
$s->setPeriod('2022-01-03T00:00+0000', '2022-01-03T00:30+0000', 1);
$s->setPeriod('2022-01-03T12:00+0000', '2022-01-03T13:30+0100', 2);
$s->setPeriod('2022-03-27T00:00', '2022-03-27 03:00', 3);
$s->setPeriod('00:00', '00:30', 4);
$s->setPeriod('12:00', '13:30', 5);
echo $s->prettyPrint(true);

$daytime = new Schedule();
$daytime->setTimezone('Europe/London');
$daytime->setPoint('00:00:00', 'OFF');
$daytime->setPoint('07:00:00', 'ON');
$daytime->setPoint('22:00:00', 'OFF');
echo $daytime->prettyPrint(true);

$sd_daytime = new DailyScheduler('daytime.scheduler', 'energy.schedules', 'daytime', $daytime);

$sd_daytime->reschedule();
$sd_daytime->reschedule('2022-03-26');
$sd_daytime->reschedule('2022-03-27');
$sd_daytime->reschedule('2022-03-28');
$sd_daytime->reschedule('2022-10-30');

exit;
