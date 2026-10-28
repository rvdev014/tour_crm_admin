<?php

namespace Tests\Feature;

use App\Models\Destination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class OptionalRussianContentTest extends TestCase
{
    use RefreshDatabase;

    private function englishOnlyDestination(): Destination
    {
        return Destination::create([
            'title_en' => 'Samarkand',
            'slug' => 'samarkand-'.uniqid(),
            'is_published' => true,
        ]);
    }

    public function test_a_destination_can_be_saved_without_russian(): void
    {
        $destination = $this->englishOnlyDestination();

        $this->assertNull($destination->fresh()->title_ru);
        $this->assertSame('Samarkand', $destination->fresh()->title_en);
    }

    public function test_russian_visitors_see_english_when_russian_is_missing(): void
    {
        $destination = $this->englishOnlyDestination()->fresh();

        App::setLocale('ru');
        $this->assertSame('Samarkand', $destination->title);
    }

    public function test_russian_text_still_wins_when_it_is_filled_in(): void
    {
        $destination = $this->englishOnlyDestination();
        $destination->update(['title_ru' => 'Самарканд']);

        App::setLocale('ru');
        $this->assertSame('Самарканд', $destination->fresh()->title);

        App::setLocale('en');
        $this->assertSame('Samarkand', $destination->fresh()->title);
    }
}
