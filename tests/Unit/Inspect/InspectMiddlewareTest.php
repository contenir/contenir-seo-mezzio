<?php

declare(strict_types=1);

namespace Contenir\Seo\Mezzio\Tests\Unit\Inspect;

use Contenir\Preview\Mezzio\PreviewMiddleware;
use Contenir\Preview\Mezzio\TargetMatcher;
use Contenir\Preview\PreviewConfig;
use Contenir\Preview\PreviewContext;
use Contenir\Preview\PreviewEntry;
use Contenir\Preview\PreviewRecord;
use Contenir\Preview\Resolver\PathResolver;
use Contenir\Preview\Resolver\ResolverRegistry;
use Contenir\Preview\Token\TokenSigner;
use Contenir\Preview\Token\TokenVerifier;
use Contenir\Seo\Mezzio\Inspect\InspectMiddleware;
use Contenir\Seo\Mezzio\Tests\TestAsset\FixedClock;
use Contenir\Seo\Mezzio\Tests\TestAsset\HandlerResolver;
use Contenir\Seo\Mezzio\Tests\TestAsset\RobotsHeader;
use Contenir\SiteToken\Replay\InMemoryReplayGuard;
use Laminas\Diactoros\Response\HtmlResponse;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\StreamFactory;
use Mezzio\Router\Route;
use Mezzio\Router\RouteResult;
use Mezzio\Router\RouterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SensitiveParameter;

use function array_key_exists;
use function json_decode;
use function str_contains;

#[Group('unit')]
final class InspectMiddlewareTest extends TestCase
{
    private const string SHARED = 'a-secret-of-at-least-thirty-two-bytes!!';

    private ?ServerRequestInterface $rendered = null;

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function pathProvider(): array
    {
        return [
            'the record\'s path'      => ['/work/house', '/work/house'],
            'none'                    => [null, InspectMiddleware::ENDPOINT],
            'not text'                => [['/work/house'], InspectMiddleware::ENDPOINT],
            'not from the root'       => ['work/house', InspectMiddleware::ENDPOINT],
            'another host\'s address' => ['//elsewhere.test/house', InspectMiddleware::ENDPOINT],
        ];
    }

    /**
     * @return array<string, array{?string}>
     */
    public static function robotsHeaderProvider(): array
    {
        return ['sent' => ['noindex, follow'], 'none' => [null]];
    }

    private static function config(): PreviewConfig
    {
        return new PreviewConfig('https://admin.site.test', 'https://www.site.test', ['k1' => self::SHARED], 'k1');
    }

    /**
     * @return array<string, mixed>
     */
    private static function json(ResponseInterface $response): array
    {
        /** @var array<string, mixed> */
        return json_decode((string) $response->getBody(), associative: true);
    }

    /**
     * @param array<string, mixed> $json
     *
     * @return list<mixed>
     */
    private static function pick(array $json, string ...$keys): array
    {
        $picked = [];
        foreach ($keys as $key) {
            $picked[] = $json[$key] ?? null;
        }

        return $picked;
    }

    private static function token(string $section = 'site.page.home'): string
    {
        return (new TokenSigner(self::config(), new FixedClock()))->sign(
            new PreviewRecord($section, ['resource_id' => '7'], ['slug' => 'spa', 'title' => 'Edited title']),
            '12',
        );
    }

    #[Test]
    public function answersWithTheHeadOfTheRecordsPageAsTheSiteRendersIt(): void
    {
        $response = $this->middleware()->process(
            $this->posted(self::token()),
            $this->site(
                '<html><head><title>Edited title | Site</title><link rel="canonical" href="https://www.site.test/spa"></head></html>',
                404,
            ),
        );

        static::assertSame(
            [200, 'application/json', 'no-store', 'noindex', 404, 'Edited title | Site', 'https://www.site.test/spa'],
            [
                $response->getStatusCode(),
                $response->getHeaderLine('Content-Type'),
                $response->getHeaderLine('Cache-Control'),
                $response->getHeaderLine('X-Robots-Tag'),
                ...self::pick(self::json($response), 'status', 'title', 'canonical'),
            ],
        );
    }

    #[Test]
    #[DataProvider('robotsHeaderProvider')]
    public function answersWithTheRobotsHeaderTheSitesSeoSendsThePage(?string $robots): void
    {
        $response = $this->middleware($robots)->process($this->posted(self::token()), $this->site('<html></html>'));

        static::assertSame($robots, self::json($response)['robotsHeader']);
    }

    #[Test]
    public function aPathTheSiteResolvesForTheRecordWinsOverThePostedOne(): void
    {
        $this->middleware()->process(
            $this->posted(self::token())->withParsedBody([
                'contenir_preview'            => self::token(),
                InspectMiddleware::PATH_FIELD => '/work/house',
            ]),
            $this->site('<html></html>'),
        );

        static::assertSame('/spa', $this->rendered?->getUri()->getPath());
    }

    #[Test]
    public function aRequestWithoutATokenIsRefused(): void
    {
        $response = $this->middleware()->process(
            (new ServerRequest(
                uri: 'https://www.site.test' . InspectMiddleware::ENDPOINT,
                method: 'POST',
            ))
                ->withParsedBody(['contenir_preview' => ['not', 'text']]),
            $this->site('<html></html>'),
        );

        static::assertSame([400, null], [$response->getStatusCode(), $this->rendered]);
    }

    #[Test]
    public function aTokenTheSiteRefusesIsAnsweredWithTheRefusal(): void
    {
        $response = $this->middleware()->process(
            $this->posted('not-a-token'),
            $this->site('<html><title>Never</title></html>'),
        );

        static::assertSame(
            [400, true, null],
            [$response->getStatusCode(), array_key_exists('error', self::json($response)), $this->rendered],
        );
    }

    #[Test]
    public function otherRequestsPassThrough(): void
    {
        $response = $this->middleware()->process(
            new ServerRequest(
                uri: 'https://www.site.test/about',
                method: 'GET',
            ),
            $this->site('<html>About</html>'),
        );

        static::assertSame(['text/html; charset=utf-8', '/about'], [
            $response->getHeaderLine('Content-Type'),
            $this->rendered?->getUri()->getPath(),
        ]);
    }

    #[Test]
    #[DataProvider('pathProvider')]
    public function rendersAPageDispatchedWithoutRoutingAtThePathTheCmsPosted(mixed $path, string $expected): void
    {
        $this->middleware()->process(
            $this->posted(self::token('site.page.resource'))->withParsedBody([
                'contenir_preview'            => self::token('site.page.resource'),
                InspectMiddleware::PATH_FIELD => $path,
            ]),
            $this->site('<html></html>'),
        );

        static::assertSame($expected, $this->rendered?->getUri()->getPath());
    }

    #[Test]
    public function rendersTheRecordsPageAsAPreviewDoes(): void
    {
        $this->middleware()->process($this->posted(self::token()), $this->site('<html></html>'));

        static::assertSame(
            ['GET', '/spa', PreviewMiddleware::REQUEST_CACHE_CONTROL, []],
            [
                $this->rendered?->getMethod(),
                $this->rendered?->getUri()->getPath(),
                $this->rendered?->getHeaderLine('Cache-Control'),
                $this->rendered?->getParsedBody(),
            ],
        );
    }

    public function saw(ServerRequestInterface $request): void
    {
        $this->rendered = $request;
    }

    #[Test]
    public function writesAddressesAndTextWithoutEscapingThem(): void
    {
        $response = $this->middleware()->process(
            $this->posted(self::token()),
            $this->site(
                '<html><head><title>Café</title><link rel="canonical" href="https://www.site.test/spa"></head></html>',
            ),
        );

        static::assertSame(
            [true, true],
            [
                str_contains((string) $response->getBody(), '"https://www.site.test/spa"'),
                str_contains((string) $response->getBody(), '"Café"'),
            ],
        );
    }

    private function middleware(?string $robots = null): InspectMiddleware
    {
        $clock  = new FixedClock();
        $router = $this->createStub(RouterInterface::class);
        $router->method('match')
            ->willReturn(RouteResult::fromRoute(new Route(
                '/{slug}',
                $this->createStub(MiddlewareInterface::class),
            )));

        return new InspectMiddleware(
            new PreviewEntry(
                new TokenVerifier(self::config(), $clock, new InMemoryReplayGuard($clock)),
                new ResolverRegistry([
                    'site.page.home'     => new PathResolver(),
                    'site.page.resource' => new HandlerResolver(),
                ]),
                new PreviewContext(),
            ),
            new TargetMatcher($router),
            new ResponseFactory(),
            new StreamFactory(),
            new RobotsHeader($robots),
        );
    }

    private function posted(#[SensitiveParameter] string $token): ServerRequest
    {
        return (new ServerRequest(
            uri: 'https://www.site.test' . InspectMiddleware::ENDPOINT,
            method: 'POST',
        ))
            ->withParsedBody(['contenir_preview' => $token]);
    }

    private function site(string $html, int $status = 200): RequestHandlerInterface
    {
        return new readonly class($this, $html, $status) implements RequestHandlerInterface {
            public function __construct(
                private InspectMiddlewareTest $test,
                private string $html,
                private int $status,
            ) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->test->saw($request);

                return new HtmlResponse($this->html, $this->status);
            }
        };
    }
}
