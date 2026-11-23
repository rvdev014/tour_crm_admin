<?php

namespace Tests\Feature;

use App\Models\Destination;
use App\Models\DestinationDish;
use App\Models\DestinationPlace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestinationGuideApiTest extends TestCase
{
    use RefreshDatabase;

    private function country(array $attributes = []): Destination
    {
        return Destination::create($attributes + [
            'title_en' => 'Kazakhstan',
            'slug' => 'kazakhstan-'.uniqid(),
            'is_published' => true,
        ]);
    }

    private function fetch(Destination $destination, string $lang = 'en')
    {
        return $this->getJson('/api/destinations/'.$destination->slug, ['Lang' => $lang]);
    }

    public function test_places_dishes_seasons_and_map_are_returned(): void
    {
        $country = $this->country([
            'cuisine_intro_en' => 'Meat-heavy cuisine.',
            'season_winter_en' => 'Skiing.',
            'season_summer_en' => 'Hot in the south.',
            'map_photo' => 'destinations/maps/kz.png',
        ]);
        DestinationPlace::create(['destination_id' => $country->id, 'category' => 'outdoor', 'title_en' => 'Burabai', 'order' => 2]);
        DestinationPlace::create(['destination_id' => $country->id, 'category' => 'outdoor', 'title_en' => 'Chimbulak', 'order' => 1]);
        DestinationPlace::create(['destination_id' => $country->id, 'category' => 'historical', 'title_en' => 'Turkestan', 'order' => 3]);
        DestinationDish::create(['destination_id' => $country->id, 'name_en' => 'Beshparmak', 'description_en' => 'National dish', 'order' => 1]);

        $response = $this->fetch($country)->assertOk();

        $response->assertJsonPath('data.cuisine_intro', 'Meat-heavy cuisine.')
            ->assertJsonPath('data.seasons.winter', 'Skiing.')
            ->assertJsonPath('data.seasons.summer', 'Hot in the south.')
            ->assertJsonPath('data.seasons.spring', null)
            ->assertJsonPath('data.dishes.0.name', 'Beshparmak')
            ->assertJsonCount(3, 'data.places')
            ->assertJsonPath('data.places.0.title', 'Chimbulak')
            ->assertJsonPath('data.places.1.title', 'Burabai')
            ->assertJsonPath('data.places.0.category', 'outdoor')
            ->assertJsonPath('data.places.0.category_title', 'Top outdoor experiences')
            ->assertJsonPath('data.places.2.category', 'historical');

        $this->assertStringEndsWith('storage/destinations/maps/kz.png', $response->json('data.map_photo'));
    }

    public function test_seasons_is_null_when_nothing_is_filled_in(): void
    {
        $this->fetch($this->country())
            ->assertOk()
            ->assertJsonPath('data.seasons', null)
            ->assertJsonPath('data.map_photo', null)
            ->assertJsonCount(0, 'data.places')
            ->assertJsonCount(0, 'data.dishes');
    }

    public function test_russian_falls_back_to_english_and_uses_russian_when_present(): void
    {
        $country = $this->country();
        DestinationPlace::create(['destination_id' => $country->id, 'category' => 'cultural', 'title_en' => 'Green Bazaar', 'title_ru' => 'Зелёный базар', 'order' => 1]);
        DestinationPlace::create(['destination_id' => $country->id, 'category' => 'cultural', 'title_en' => 'Bayterek', 'order' => 2]);

        $this->fetch($country, 'ru')
            ->assertOk()
            ->assertJsonPath('data.places.0.title', 'Зелёный базар')
            ->assertJsonPath('data.places.1.title', 'Bayterek')
            ->assertJsonPath('data.places.0.category_title', 'Лучшие культурные впечатления');
    }

    public function test_place_card_links_only_to_published_pages(): void
    {
        $country = $this->country(['slug' => 'kz-link']);
        $published = Destination::create(['parent_id' => $country->id, 'title_en' => 'Almaty', 'slug' => 'almaty', 'is_published' => true]);
        $draft = Destination::create(['parent_id' => $country->id, 'title_en' => 'Draft', 'slug' => 'draft', 'is_published' => false]);

        DestinationPlace::create(['destination_id' => $country->id, 'category' => 'cultural', 'title_en' => 'A', 'link_destination_id' => $published->id, 'order' => 1]);
        DestinationPlace::create(['destination_id' => $country->id, 'category' => 'cultural', 'title_en' => 'B', 'link_destination_id' => $draft->id, 'order' => 2]);
        DestinationPlace::create(['destination_id' => $country->id, 'category' => 'cultural', 'title_en' => 'C', 'order' => 3]);

        $this->fetch($country)
            ->assertOk()
            ->assertJsonPath('data.places.0.link_slug', 'almaty')
            ->assertJsonPath('data.places.0.link_country_slug', 'kz-link')
            ->assertJsonPath('data.places.1.link_slug', null)
            ->assertJsonPath('data.places.1.link_country_slug', null)
            ->assertJsonPath('data.places.2.link_slug', null);
    }

    public function test_the_list_endpoint_does_not_ship_places_or_dishes(): void
    {
        $country = $this->country();
        DestinationPlace::create(['destination_id' => $country->id, 'category' => 'outdoor', 'title_en' => 'X']);

        $item = $this->getJson('/api/destinations')->assertOk()->json('data.0');

        $this->assertArrayNotHasKey('places', $item);
        $this->assertArrayNotHasKey('dishes', $item);
    }
}
