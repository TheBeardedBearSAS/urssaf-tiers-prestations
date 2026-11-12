<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Scheduler;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Messenger\RunCommandContext;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Schedule;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\DependencyInjection\ConfigurationRules;
use TheBeardedBear\UrssafTiersPrestations\Bridge\Symfony\Scheduler\UrssafScheduleProvider;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\App\TestKernel;
use TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support\Harness;

final class UrssafScheduleProviderTest extends TestCase
{
    public function testEveryCronOfThePackageIsScheduledAtItsDefaultFrequency(): void
    {
        $schedule = (new UrssafScheduleProvider(['submit_pending' => 'PT5M', 'sync_pairing' => 'PT10M', 'sync_statuses' => 'PT15M', 'reconcile_stale' => 'PT15M']))->getSchedule();

        static::assertSame([
            'urssaf:customers:submit-pending every 300 seconds (PT5M)',
            'urssaf:payment-requests:submit-pending every 300 seconds (PT5M)',
            'urssaf:customers:sync-pairing every 600 seconds (PT10M)',
            'urssaf:payment-requests:sync-statuses every 900 seconds (PT15M)',
            'urssaf:customers:reconcile-stale every 900 seconds (PT15M)',
            'urssaf:payment-requests:reconcile-stale every 900 seconds (PT15M)',
        ], self::describe($schedule));
    }

    public function testATaskWithoutFrequencyIsLeftOut(): void
    {
        $schedule = (new UrssafScheduleProvider(['submit_pending' => null, 'sync_pairing' => 'PT1H', 'sync_statuses' => null]))->getSchedule();

        static::assertSame(['urssaf:customers:sync-pairing every 3600 seconds (PT1H)'], self::describe($schedule));
    }

    public function testTheProviderIsRegisteredUnderTheConfiguredScheduleName(): void
    {
        $enabled = (new TestKernel(['scheduler' => ['enabled' => true, 'name' => 'billing', 'frequencies' => ['sync_statuses' => 'PT30M', 'reconcile_stale' => null]]]))->definitions();
        $disabled = (new TestKernel())->definitions();

        $definition = $enabled->getDefinition(UrssafScheduleProvider::class);
        static::assertSame(['scheduler.schedule_provider' => [['name' => 'billing']]], $definition->getTags());
        static::assertEquals([['submit_pending' => 'PT5M', 'sync_pairing' => 'PT10M', 'sync_statuses' => 'PT30M', 'reconcile_stale' => null, 'relay_outbox' => null, 'purge_outbox' => null]], $definition->getArguments());
        static::assertFalse($disabled->hasDefinition(UrssafScheduleProvider::class));
    }

    public function testTheScheduledMessagesRunTheCommands(): void
    {
        $harness = Harness::boot(['scheduler' => ['enabled' => true]], false, true);

        $envelope = $harness->privateService('messenger.default_bus', MessageBusInterface::class)->dispatch(new RunCommandMessage('urssaf:status'));

        // @mago-expect analysis:mixed-assignment The handler result is untyped: the assertion below narrows it.
        $context = $envelope->last(HandledStamp::class)?->getResult();
        static::assertInstanceOf(RunCommandContext::class, $context);
        static::assertSame(0, $context->exitCode);
        static::assertStringContainsString('Customer registrations', $context->output);
    }

    public function testTheSchedulerNeedsItsComponent(): void
    {
        static::assertTrue(ConfigurationRules::schedulerRequiredButMissing(['scheduler' => ['enabled' => true]], false));
        static::assertFalse(ConfigurationRules::schedulerRequiredButMissing(['scheduler' => ['enabled' => true]], true));
        static::assertFalse(ConfigurationRules::schedulerRequiredButMissing(['scheduler' => ['enabled' => false]], false));
        static::assertFalse(ConfigurationRules::schedulerEnabledWithoutComponent(['scheduler' => ['enabled' => true]]));
    }

    /**
     * @return list<string> "<command> <trigger>" of every recurring message
     */
    private static function describe(Schedule $schedule): array
    {
        $described = [];
        foreach ($schedule->getRecurringMessages() as $recurring) {
            $context = new MessageContext('urssaf', $recurring->getId(), $recurring->getTrigger(), new DateTimeImmutable());
            $messages = iterator_to_array($recurring->getProvider()->getMessages($context), false);
            static::assertCount(1, $messages);
            $message = reset($messages);
            static::assertInstanceOf(RunCommandMessage::class, $message);
            $described[] = $message->input . ' ' . $recurring->getTrigger()->__toString();
        }

        return $described;
    }
}
