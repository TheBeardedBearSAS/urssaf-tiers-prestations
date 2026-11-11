<?php

declare(strict_types=1);

namespace TheBeardedBear\UrssafTiersPrestations\Tests\Functional\Symfony\Support;

use Http\Message\RequestMatcher\RequestMatcher;
use Http\Mock\Client;

/**
 * Scripted URSSAF platform behind the mocked PSR-18 client. Every route answers a success by default and can be
 * switched to a given status or to a transport failure. Requests are recorded per route.
 */
final class HttpScript
{
    public const ROUTE_AUTHENTICATION = 'authentication';
    public const ROUTE_REGISTER = '010';
    public const ROUTE_PAIRING = '020';
    public const ROUTE_SUBMIT = '050';
    public const ROUTE_SEARCH = '070';

    public const URSSAF_CUSTOMER_ID = 'af0be430-908a-11eb-a8b3-0242ac130003';
    public const URSSAF_PAYMENT_REQUEST_ID = 'aaaa1111-2222-3b3b-4d4-55e666ffff77';

    /** @var array<string, RouteEndpoint> */
    private array $routes;

    public function __construct(Client $client)
    {
        $this->routes = [
            self::ROUTE_AUTHENTICATION => new RouteEndpoint(200, '{"access_token":"fictitious-access-token","token_type":"Bearer","expires_in":3600}'),
            self::ROUTE_REGISTER => new RouteEndpoint(200, \sprintf('{"idClient":"%s"}', self::URSSAF_CUSTOMER_ID)),
            self::ROUTE_PAIRING => new RouteEndpoint(200, self::pairingBody('APPAREILLAGE_VALIDE', 'Transmission DP possible depuis le 15/01/2022 11:00:00')),
            self::ROUTE_SUBMIT => new RouteEndpoint(200, \sprintf(
                '[{"idClient":"%s","idDemandePaiement":"%s","numFactureTiers":"2022-AZ-00001","statut":"10","errors":[]}]',
                self::URSSAF_CUSTOMER_ID,
                self::URSSAF_PAYMENT_REQUEST_ID,
            )),
            self::ROUTE_SEARCH => new RouteEndpoint(200, \sprintf(
                '{"errors":[],"infoDemandePaiements":[{"idDemandePaiement":"%s","demandePaiement":{"numFactureTiers":"2022-AZ-00001","mntFactureTTC":11.02},"statut":{"code":"70","libelle":"Payee"},"infoVirement":{"mntVirement":8.27,"dateVirement":"2022-02-20T00:00:00Z"}}]}',
                self::URSSAF_PAYMENT_REQUEST_ID,
            )),
        ];
        $matchers = [
            self::ROUTE_AUTHENTICATION => new RequestMatcher('/oauth/v1/token$', null, ['POST']),
            self::ROUTE_REGISTER => new RequestMatcher('/particulier$', null, ['POST']),
            self::ROUTE_PAIRING => new RequestMatcher('/particulier$', null, ['GET']),
            self::ROUTE_SEARCH => new RequestMatcher('/demandePaiement/rechercher$', null, ['POST']),
            self::ROUTE_SUBMIT => new RequestMatcher('/demandePaiement$', null, ['POST']),
        ];
        foreach ($matchers as $route => $matcher) {
            $client->on($matcher, $this->routes[$route]);
        }
    }

    public function calls(string $route): int
    {
        return $this->routes[$route]->calls();
    }

    /**
     * @param array<string, string> $headers
     */
    public function respond(string $route, int $status, string $body, array $headers = []): void
    {
        $this->routes[$route]->respond($status, $body, $headers);
    }

    public function failTransport(string $route): void
    {
        $this->routes[$route]->failTransport();
    }

    public static function pairingBody(string $code, string $description): string
    {
        return \sprintf('{"idClient":"%s","statut":{"code":"%s","description":"%s","etat":"OK"}}', self::URSSAF_CUSTOMER_ID, $code, $description);
    }

}
