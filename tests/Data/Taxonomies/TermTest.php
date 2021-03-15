<?php

namespace Tests\Data\Taxonomies;

use Facades\Statamic\Fields\BlueprintRepository;
use Illuminate\Support\Facades\Event;
use Mockery;
use Statamic\Events\TermCreated;
use Statamic\Events\TermSaved;
use Statamic\Events\TermSaving;
use Statamic\Facades;
use Statamic\Facades\Taxonomy;
use Statamic\Fields\Blueprint;
use Statamic\Taxonomies\Taxonomy as TaxonomiesTaxonomy;
use Statamic\Taxonomies\Term;
use Tests\PreventSavingStacheItemsToDisk;
use Tests\TestCase;

class TermTest extends TestCase
{
    use PreventSavingStacheItemsToDisk;

    /** @test */
    public function it_gets_the_blueprint_when_defined_on_itself()
    {
        BlueprintRepository::shouldReceive('in')->with('taxonomies/tags')->andReturn(collect([
            'first' => $first = (new Blueprint)->setHandle('first'),
            'second' => $second = (new Blueprint)->setHandle('second'),
        ]));
        Taxonomy::make('tags')->save();
        $term = (new Term)->taxonomy('tags')->blueprint('second');

        $this->assertSame($second, $term->blueprint());
        $this->assertNotSame($first, $second);
    }

    /** @test */
    public function it_gets_the_blueprint_when_defined_in_a_value()
    {
        BlueprintRepository::shouldReceive('in')->with('taxonomies/tags')->andReturn(collect([
            'first' => $first = (new Blueprint)->setHandle('first'),
            'second' => $second = (new Blueprint)->setHandle('second'),
        ]));
        Taxonomy::make('tags')->save();
        $term = (new Term)->taxonomy('tags')->set('blueprint', 'second');

        $this->assertSame($second, $term->blueprint());
        $this->assertNotSame($first, $second);
    }

    /** @test */
    public function it_gets_the_default_taxonomy_blueprint_when_undefined()
    {
        BlueprintRepository::shouldReceive('in')->with('taxonomies/tags')->andReturn(collect([
            'first' => $first = (new Blueprint)->setHandle('first'),
            'second' => $second = (new Blueprint)->setHandle('second'),
        ]));
        $taxonomy = tap(Taxonomy::make('tags'))->save();
        $term = (new Term)->taxonomy($taxonomy);

        $this->assertSame($first, $term->blueprint());
        $this->assertNotSame($first, $second);
    }

    /** @test */
    public function the_blueprint_is_blinked_when_getting_and_flushed_when_setting()
    {
        $term = (new Term)->taxonomy('tags');
        $taxonomy = Mockery::mock(Taxonomy::make('tags'));
        $taxonomy->shouldReceive('termBlueprint')->with(null, $term)->once()->andReturn('the old blueprint');
        $taxonomy->shouldReceive('termBlueprint')->with('new', $term)->once()->andReturn('the new blueprint');
        Taxonomy::shouldReceive('findByHandle')->with('tags')->andReturn($taxonomy);

        $this->assertEquals('the old blueprint', $term->blueprint());
        $this->assertEquals('the old blueprint', $term->blueprint());

        $term->blueprint('new');

        $this->assertEquals('the new blueprint', $term->blueprint());
        $this->assertEquals('the new blueprint', $term->blueprint());
    }

    /** @test */
    public function it_gets_the_entry_count_through_the_repository()
    {
        $term = (new Term)->taxonomy('tags')->slug('foo');

        $mock = \Mockery::mock(Facades\Term::getFacadeRoot())->makePartial();
        Facades\Term::swap($mock);
        $mock->shouldReceive('entriesCount')->with($term)->andReturn(7)->once();

        $this->assertEquals(7, $term->entriesCount());
        $this->assertEquals(7, $term->entriesCount());
    }

    /** @test */
    public function it_saves_through_the_api()
    {
        Event::fake();

        $taxonomy = (new TaxonomiesTaxonomy)->handle('tags')->save();
        $term = (new Term)->taxonomy('tags')->slug('foo')->data(['foo' => 'bar']);

        $return = $term->save();

        $this->assertTrue($return);

        Event::assertDispatched(TermSaving::class, function ($event) use ($term) {
            return $event->term === $term;
        });

        Event::assertDispatched(TermCreated::class, function ($event) use ($term) {
            return $event->term === $term;
        });

        Event::assertDispatched(TermSaved::class, function ($event) use ($term) {
            return $event->term === $term;
        });
    }

    /** @test */
    public function it_dispatches_term_created_only_once()
    {
        Event::fake();

        $taxonomy = (new TaxonomiesTaxonomy)->handle('tags')->save();
        $term = (new Term)->taxonomy('tags')->slug('foo')->data(['foo' => 'bar']);

        $term->save();
        $term->save();
        $term->save();

        Event::assertDispatched(TermSaved::class, 3);
        Event::assertDispatched(TermCreated::class, 1);
    }

    /** @test */
    public function it_saves_quietly()
    {
        Event::fake();

        $taxonomy = (new TaxonomiesTaxonomy)->handle('tags')->save();
        $term = (new Term)->taxonomy('tags')->slug('foo')->data(['foo' => 'bar']);

        $return = $term->saveQuietly();

        $this->assertTrue($return);

        Event::assertNotDispatched(TermSaving::class);
        Event::assertNotDispatched(TermSaved::class);
        Event::assertNotDispatched(TermCreated::class);
    }
}
