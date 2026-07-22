<?php

declare(strict_types=1);

namespace QUI\ERP\Order\SimpleCheckout\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI\ERP\Order\SimpleCheckout\Events;
use QUI\Projects\Media;
use QUI\Projects\Media\Image;
use QUI\Projects\Project;
use QUI\Projects\Site\Edit;
use QUI\Bricks\Manager as BrickManager;
use QUI\Permissions\Permission;
use ReflectionProperty;

final class EventsTest extends TestCase
{
    public static function areaProvider(): iterable
    {
        yield 'empty attribute' => [null, []];
        yield 'invalid JSON' => ['not-json', []];
        yield 'existing areas' => [
            '{"content":[{"brickId":3,"customFields":"","uid":""}]}',
            ['content' => [['brickId' => 3, 'customFields' => '', 'uid' => '']]]
        ];
    }

    #[DataProvider('areaProvider')]
    public function testAddBrickToSiteNormalizesAndExtendsAreas(mixed $storedAreas, array $expectedAreas): void
    {
        $Site = $this->createMock(Edit::class);
        $Site->expects(self::once())
            ->method('getAttribute')
            ->with('quiqqer.bricks.areas')
            ->willReturn($storedAreas);
        $Site->expects(self::once())
            ->method('setAttribute')
            ->with(
                'quiqqer.bricks.areas',
                self::callback(function (string $json) use ($expectedAreas): bool {
                    $expectedAreas['headerSuffix'][] = [
                        'brickId' => 17,
                        'customFields' => '',
                        'uid' => ''
                    ];

                    self::assertSame($expectedAreas, json_decode($json, true, 512, JSON_THROW_ON_ERROR));

                    return true;
                })
            );

        Events::addBrickToSite($Site, 17, 'headerSuffix');
    }

    public function testGetDemoBricksDataUsesProjectPlaceholder(): void
    {
        $Image = $this->createMock(Image::class);
        $Image->method('getUrl')->willReturn('/media/placeholder.png');

        $Media = $this->createMock(Media::class);
        $Media->method('getPlaceholderImage')->willReturn($Image);

        $Project = $this->createMock(Project::class);
        $Project->method('getMedia')->willReturn($Media);

        $Site = $this->createMock(Edit::class);
        $Site->method('getProject')->willReturn($Project);
        $Site->method('getId')->willReturn(42);

        $bricks = Events::getDemoBricksData($Site);

        self::assertCount(6, $bricks);
        self::assertSame('headerSuffix', $bricks[0]['assignedBrickArea']);
        self::assertSame('/media/placeholder.png', $bricks[0]['settings']['image']);
        self::assertStringContainsString('Seite 42', $bricks[0]['attributes']['description']);
        self::assertSame(
            '\\QUI\\Bricks\\Controls\\Slider\\CustomerReviewsSlider',
            $bricks[5]['attributes']['type']
        );
    }

    public function testSiteCreateChildEndIgnoresOtherSiteTypes(): void
    {
        $Site = $this->createMock(Edit::class);
        $Site->expects(self::once())
            ->method('getAttribute')
            ->with('type')
            ->willReturn('quiqqer/core:types/page');
        $Site->expects(self::never())->method('save');

        Events::siteCreateChildEnd($Site);
    }

    public function testSiteCreateChildEndCreatesAndAssignsDemoBricks(): void
    {
        $Image = $this->createStub(Image::class);
        $Image->method('getUrl')->willReturn('/media/placeholder.png');

        $Media = $this->createStub(Media::class);
        $Media->method('getPlaceholderImage')->willReturn($Image);

        $Project = $this->createStub(Project::class);
        $Project->method('getMedia')->willReturn($Media);

        $areas = null;
        $Site = $this->createMock(Edit::class);
        $Site->method('getProject')->willReturn($Project);
        $Site->method('getId')->willReturn(42);
        $Site->method('getAttribute')->willReturnCallback(
            static function (string $name) use (&$areas): mixed {
                return $name === 'type'
                    ? 'quiqqer/order-simple-checkout:types/productLandingPage'
                    : $areas;
            }
        );
        $Site->expects(self::exactly(6))
            ->method('setAttribute')
            ->willReturnCallback(static function (string $name, string $value) use (&$areas): void {
                self::assertSame('quiqqer.bricks.areas', $name);
                $areas = $value;
            });
        $Site->expects(self::once())
            ->method('save')
            ->with(\QUI::getUsers()->getSystemUser());

        $nextBrickId = 0;
        $Manager = $this->createMock(BrickManager::class);
        $Manager->expects(self::exactly(6))
            ->method('createBrickForProject')
            ->willReturnCallback(static function (Project $BrickProject) use ($Project, &$nextBrickId): int {
                self::assertSame($Project, $BrickProject);

                return ++$nextBrickId;
            });
        $Manager->expects(self::exactly(12))->method('saveBrick');

        $originalManager = BrickManager::$BrickManager;
        $PermissionUser = new ReflectionProperty(Permission::class, 'User');
        $originalPermissionUser = $PermissionUser->getValue();

        try {
            BrickManager::$BrickManager = $Manager;
            Events::siteCreateChildEnd($Site);
        } finally {
            BrickManager::$BrickManager = $originalManager;
            $PermissionUser->setValue(null, $originalPermissionUser);
        }

        $decodedAreas = json_decode((string)$areas, true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(6, $decodedAreas['headerSuffix']);
        self::assertSame([1, 2, 3, 4, 5, 6], array_column($decodedAreas['headerSuffix'], 'brickId'));
    }
}
