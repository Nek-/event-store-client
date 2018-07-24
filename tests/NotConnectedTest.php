<?php
/**
 * This file is part of the prooph/event-store-client.
 * (c) 2018-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2018-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ProophTest\EventStoreClient;

use Amp\Deferred;
use Amp\Loop;
use Amp\TimeoutException;
use PHPUnit\Framework\TestCase;
use Prooph\EventStoreClient\ConnectionSettingsBuilder;
use Prooph\EventStoreClient\EventStoreConnectionBuilder;
use Prooph\EventStoreClient\IpEndPoint;
use function Amp\Promise\timeout;

class NotConnectedTest extends TestCase
{
    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function should_timeout_connection_after_configured_amount_time_on_conenct(): void
    {
        Loop::run(function () {
            $settingsBuilder = (new ConnectionSettingsBuilder())
                ->limitReconnectionsTo(0)
                ->setReconnectionDelayTo(0)
                ->failOnNoServerResponse()
                ->withConnectionTimeoutOf(1000);

            $ip = '8.8.8.8'; //NOTE: This relies on Google DNS server being configured to swallow nonsense traffic
            $port = 4567;

            $connection = EventStoreConnectionBuilder::createAsyncFromIpEndPoint(
                new IpEndPoint($ip, $port),
                $settingsBuilder->build(),
                'test-connection'
            );

            $deferred = new Deferred();

            $connection->onConnected(function () {
                \var_dump('connected');
            });

            $connection->onReconnecting(function () {
                \var_dump('reconnecting');
            });

            $connection->onDisconnected(function () {
                \var_dump('disconnected');
            });

            $connection->onErrorOccurred(function () {
                \var_dump('error');
            });

            $connection->onClosed(function () use ($deferred) {
                $deferred->resolve();
            });

            yield $connection->connectAsync();

            try {
                yield timeout($deferred->promise(), 5000);
            } catch (TimeoutException $e) {
                $this->fail('Connection timeout took too long');
            }
        });
    }
}
