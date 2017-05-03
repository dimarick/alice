<?php

/*
 * This file is part of the Alice package.
 *
 * (c) Nelmio <hello@nelm.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Nelmio\Alice\Generator\ObjectGenerator;

use Nelmio\Alice\Definition\Object\CompleteObject;
use Nelmio\Alice\FixtureBag;
use Nelmio\Alice\Generator\Caller\SimpleCaller;
use Nelmio\Alice\Generator\Hydrator\Property\SymfonyPropertyAccessorHydrator;
use Nelmio\Alice\Generator\Hydrator\SimpleHydrator;
use PHPUnit\Framework\TestCase;
use Nelmio\Alice\Definition\Fixture\SimpleFixture;
use Nelmio\Alice\Definition\Object\SimpleObject;
use Nelmio\Alice\Definition\SpecificationBagFactory;
use Nelmio\Alice\Generator\Caller\FakeCaller;
use Nelmio\Alice\Generator\CallerInterface;
use Nelmio\Alice\Generator\GenerationContext;
use Nelmio\Alice\Generator\Instantiator\FakeInstantiator;
use Nelmio\Alice\Generator\InstantiatorInterface;
use Nelmio\Alice\Generator\ObjectGeneratorInterface;
use Nelmio\Alice\Generator\Hydrator\FakeHydrator;
use Nelmio\Alice\Generator\HydratorInterface;
use Nelmio\Alice\Generator\ResolvedFixtureSetFactory;
use Nelmio\Alice\Generator\Resolver\Value\FakeValueResolver;
use Nelmio\Alice\ObjectBag;
use Prophecy\Argument;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorBuilder;

/**
 * @covers \Nelmio\Alice\Generator\ObjectGenerator\SimpleObjectGenerator
 */
class SimpleObjectGeneratorTest extends TestCase
{
    public function testIsAnObjectGenerator()
    {
        $this->assertTrue(is_a(SimpleObjectGenerator::class, ObjectGeneratorInterface::class, true));
    }

    /**
     * @expectedException \Nelmio\Alice\Throwable\Exception\UnclonableException
     */
    public function testIsNotClonable()
    {
        clone new SimpleObjectGenerator(new FakeValueResolver(), new FakeInstantiator(), new FakeHydrator(), new FakeCaller());
    }

    /**
     * @testdox Do a instantiate-hydrate-calls cycle to generate the object described by the fixture.
     */
    public function testGenerate()
    {
        $this->markTestIncomplete('TODO');
        $fixture = new SimpleFixture('dummy', \stdClass::class, SpecificationBagFactory::create());
        $set = ResolvedFixtureSetFactory::create();
        $context = new GenerationContext();
        $context->markIsResolvingFixture('foo');
        $instance = new \stdClass();
        $instantiatedObject = new SimpleObject($fixture->getId(), $instance);

        $instantiatorProphecy = $this->prophesize(InstantiatorInterface::class);
        $instantiatorProphecy
            ->instantiate($fixture, $set, $context)
            ->willReturn(
                $setWithInstantiatedObject = ResolvedFixtureSetFactory::create(
                    null,
                    null,
                    (new ObjectBag())->with($instantiatedObject)
                )
            )
        ;
        /** @var InstantiatorInterface $instantiator */
        $instantiator = $instantiatorProphecy->reveal();

        $hydratedObject = new SimpleObject($fixture->getId(), $instance);

        $hydratorProphecy = $this->prophesize(HydratorInterface::class);
        $hydratorProphecy
            ->hydrate($instantiatedObject, $setWithInstantiatedObject, $context)
            ->willReturn(
                $setWithHydratedObject = ResolvedFixtureSetFactory::create(
                    null,
                    null,
                    (new ObjectBag())->with($hydratedObject)
                )
            )
        ;
        /** @var HydratorInterface $hydrator */
        $hydrator = $hydratorProphecy->reveal();

        $objectAfterCalls = new SimpleObject($fixture->getId(), $instance);

        $callerProphecy = $this->prophesize(CallerInterface::class);
        $callerProphecy
            ->doCallsOn($hydratedObject, $setWithHydratedObject)
            ->willReturn(
                $setWithObjectAfterCalls = ResolvedFixtureSetFactory::create(
                    null,
                    null,
                    (new ObjectBag())->with($objectAfterCalls)
                )
            )
        ;
        /** @var CallerInterface $caller */
        $caller = $callerProphecy->reveal();

        $generator = new SimpleObjectGenerator(new FakeValueResolver(), $instantiator, $hydrator, $caller);
        $objects = $generator->generate($fixture, $set, $context);

        $this->assertEquals($setWithObjectAfterCalls->getObjects(), $objects);

        $instantiatorProphecy->instantiate(Argument::cetera())->shouldHaveBeenCalledTimes(1);
        $hydratorProphecy->hydrate(Argument::cetera())->shouldHaveBeenCalledTimes(1);
        $callerProphecy->doCallsOn(Argument::cetera())->shouldHaveBeenCalledTimes(1);
    }

    /**
     * @testdox Do a instantiate-hydrate-calls cycle to generate the object described by the fixture.
     */
    public function testGenerateNeedsCompleteGeneration()
    {
        $fixture = new SimpleFixture('dummy', \stdClass::class, SpecificationBagFactory::create());
        $set = ResolvedFixtureSetFactory::create()->withFixtures((new FixtureBag())->with($fixture));
        $context = new GenerationContext();
        $context->markIsResolvingFixture('foo');
        $instance = new \stdClass();
        $instantiatedObject = new SimpleObject($fixture->getId(), $instance);

        $instantiatorProphecy = $this->prophesize(InstantiatorInterface::class);
        $instantiatorProphecy
            ->instantiate($fixture, $set, $context)
            ->willReturn(
                $setWithInstantiatedObject = ResolvedFixtureSetFactory::create(
                    null,
                    null,
                    (new ObjectBag())->with($instantiatedObject)
                )
            )
        ;
        /** @var InstantiatorInterface $instantiator */
        $instantiator = $instantiatorProphecy->reveal();

        $generator = new SimpleObjectGenerator(new FakeValueResolver(), $instantiator, new SimpleHydrator(new SymfonyPropertyAccessorHydrator(new PropertyAccessor)), new SimpleCaller());
        $objects = $generator->generate($fixture, $set, $context);
        $context->markAsNeedsCompleteGeneration();
        $context->setToSecondPass();
        $set = $set->withObjects($objects);
        $completeObjects = $generator->generate($fixture, $set, $context);
        $context->unmarkAsNeedsCompleteGeneration();

        $this->assertInstanceOf(CompleteObject::class, $completeObjects->get($fixture));
        $this->assertInstanceOf(SimpleObject::class, $objects->get($fixture));

    }
}
