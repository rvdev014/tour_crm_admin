<?php

namespace App\Console\Commands;

use App\Models\City;
use App\Models\Country;
use App\Models\Facility;
use App\Models\Hotel;
use App\Models\HotelFacility;
use App\Models\HotelPeriod;
use App\Models\HotelRoomType;
use App\Models\ManualPhone;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class HotelBuilderCommand extends Command
{
    protected $signature = 'hotel:build {--copy=} {--interactive}';
    protected $description = 'Build hotels with a convenient builder pattern or copy existing hotels with relations';

    public function handle()
    {
        $copyId = $this->option('copy');
        $interactive = $this->option('interactive');

        if ($copyId) {
            return $this->copyHotel($copyId);
        }

        if ($interactive) {
            return $this->interactiveBuilder();
        }

        return $this->builderMode();
    }

    private function copyHotel($hotelId)
    {
        try {
            $originalHotel = Hotel::with([
                'roomTypes',
                'periods',
                'phones',
                'facilities'
            ])->findOrFail($hotelId);

            $this->info("Copying hotel: {$originalHotel->name}");

            DB::beginTransaction();

            // Copy hotel
            $newHotel = $originalHotel->replicate();
            $newHotel->name = $originalHotel->name . ' (Copy)';
            $newHotel->save();

            // Copy room types
            foreach ($originalHotel->roomTypes as $roomType) {
                $newRoomType = $roomType->replicate();
                $newRoomType->hotel_id = $newHotel->id;
                $newRoomType->save();
            }

            // Copy periods
            foreach ($originalHotel->periods as $period) {
                $newPeriod = $period->replicate();
                $newPeriod->hotel_id = $newHotel->id;
                $newPeriod->save();
            }

            // Copy phones
            foreach ($originalHotel->phones as $phone) {
                $newPhone = $phone->replicate();
                $newPhone->hotel_id = $newHotel->id;
                $newPhone->save();
            }

            // Copy facilities (many-to-many relationship)
            $facilityIds = $originalHotel->facilities->pluck('id')->toArray();
            $newHotel->facilities()->attach($facilityIds);

            DB::commit();

            $this->info("✅ Hotel copied successfully!");
            $this->line("Original: {$originalHotel->name} → New: {$newHotel->name}");
            $this->line("New Hotel ID: {$newHotel->id}");

            return 0;
        } catch (\Exception $e) {
            DB::rollback();
            $this->error("Error copying hotel: {$e->getMessage()}");
            return 1;
        }
    }

    private function interactiveBuilder()
    {
        $this->info('🏨 Interactive Hotel Builder');
        $this->line('Follow the prompts to create a new hotel.');

        $countries = Country::all();
        $cities = City::all();
        $facilities = Facility::all();

        $builder = new HotelBuilder();

        // Basic info
        $name = $this->ask('Hotel name:');
        $builder->name($name);

        $countryId = $this->choice('Select country:', $countries->pluck('name', 'id')->toArray());
        $builder->country($countryId);

        $cityId = $this->choice('Select city:', $cities->pluck('name', 'id')->toArray());
        $builder->city($cityId);

        if ($email = $this->ask('Email (optional):')) {
            $builder->email($email);
        }

        if ($address = $this->ask('Address (optional):')) {
            $builder->address($address);
        }

        if ($phone = $this->ask('Phone (optional):')) {
            $builder->phone($phone);
        }

        if ($rate = $this->ask('Rate (1-5, optional):')) {
            $builder->rate((float) $rate);
        }

        if ($this->confirm('Add facilities?')) {
            $selectedFacilities = $this->choice(
                'Select facilities (comma-separated numbers):',
                $facilities->pluck('name', 'id')->toArray(),
                null,
                null,
                true
            );
            foreach ($selectedFacilities as $facilityId) {
                $builder->addFacility($facilityId);
            }
        }

        if ($this->confirm('Add room types?')) {
            $roomTypes = RoomType::all();
            while ($this->confirm('Add another room type?')) {
                $roomTypeId = $this->choice('Select room type:', $roomTypes->pluck('name', 'id')->toArray());
                $price = (float) $this->ask('Price:');
                $builder->addRoomType($roomTypeId, $price);
            }
        }

        try {
            $hotel = $builder->build();
            $this->info("✅ Hotel created successfully!");
            $this->line("Name: {$hotel->name}");
            $this->line("Hotel ID: {$hotel->id}");
            return 0;
        } catch (\Exception $e) {
            $this->error("Error creating hotel: {$e->getMessage()}");
            return 1;
        }
    }

    private function builderMode()
    {
        $this->info('🏨 Hotel Builder Examples');
        $this->line('Here are some examples of how to use the HotelBuilder in your code:');
        $this->newLine();

        $examples = [
            'Basic Hotel:' => '
$hotel = (new HotelBuilder())
    ->name("Grand Hotel")
    ->country(1)
    ->city(1)
    ->email("info@grandhotel.com")
    ->address("123 Main Street")
    ->phone("+1234567890")
    ->rate(4.5)
    ->build();',
            
            'Copy Existing Hotel:' => '
php artisan hotel:build --copy=123',
            
            'Interactive Mode:' => '
php artisan hotel:build --interactive',
            
            'Advanced Hotel with Relations:' => '
$hotel = (new HotelBuilder())
    ->name("Luxury Resort")
    ->country(1)
    ->city(1)
    ->email("luxury@resort.com")
    ->address("Ocean View Boulevard")
    ->phone("+1234567890")
    ->rate(5.0)
    ->websitePrice(250.00)
    ->addFacility(1) // WiFi
    ->addFacility(2) // Pool
    ->addRoomType(1, 120.00) // Single room
    ->addRoomType(2, 180.00) // Double room
    ->addPhone("+1234567891", "Reception")
    ->addPhone("+1234567892", "Restaurant")
    ->build();'
        ];

        foreach ($examples as $title => $code) {
            $this->line("<fg=yellow>{$title}</>");
            $this->line("<fg=gray>{$code}</>");
            $this->newLine();
        }

        return 0;
    }
}

class HotelBuilder
{
    private array $attributes = [];
    private array $facilities = [];
    private array $roomTypes = [];
    private array $phones = [];

    public function name(string $name): self
    {
        $this->attributes['name'] = $name;
        return $this;
    }

    public function country(int $countryId): self
    {
        $this->attributes['country_id'] = $countryId;
        return $this;
    }

    public function city(int $cityId): self
    {
        $this->attributes['city_id'] = $cityId;
        return $this;
    }

    public function email(string $email): self
    {
        $this->attributes['email'] = $email;
        return $this;
    }

    public function address(string $address): self
    {
        $this->attributes['address'] = $address;
        return $this;
    }

    public function phone(string $phone): self
    {
        $this->attributes['phone'] = $phone;
        return $this;
    }

    public function rate(float $rate): self
    {
        $this->attributes['rate'] = $rate;
        return $this;
    }

    public function websitePrice(float $price): self
    {
        $this->attributes['website_price'] = $price;
        return $this;
    }

    public function contractNumber(string $number): self
    {
        $this->attributes['contract_number'] = $number;
        return $this;
    }

    public function contractDate(string $date): self
    {
        $this->attributes['contract_date'] = Carbon::parse($date);
        return $this;
    }

    public function inn(string $inn): self
    {
        $this->attributes['inn'] = $inn;
        return $this;
    }

    public function companyName(string $name): self
    {
        $this->attributes['company_name'] = $name;
        return $this;
    }

    public function comment(string $comment): self
    {
        $this->attributes['comment'] = $comment;
        return $this;
    }

    public function description(string $description): self
    {
        $this->attributes['description'] = $description;
        return $this;
    }

    public function coordinates(float $latitude, float $longitude): self
    {
        $this->attributes['latitude'] = $latitude;
        $this->attributes['longitude'] = $longitude;
        return $this;
    }

    public function addFacility(int $facilityId): self
    {
        $this->facilities[] = $facilityId;
        return $this;
    }

    public function addRoomType(int $roomTypeId, float $price, array $options = []): self
    {
        $this->roomTypes[] = array_merge([
            'room_type_id' => $roomTypeId,
            'price' => $price
        ], $options);
        return $this;
    }

    public function addPhone(string $phone, string $description = null): self
    {
        $this->phones[] = [
            'phone' => $phone,
            'description' => $description
        ];
        return $this;
    }

    public function build(): Hotel
    {
        DB::beginTransaction();
        try {
            $hotel = Hotel::create($this->attributes);

            // Add facilities
            if (!empty($this->facilities)) {
                $hotel->facilities()->attach($this->facilities);
            }

            // Add room types
            foreach ($this->roomTypes as $roomTypeData) {
                HotelRoomType::create(array_merge([
                    'hotel_id' => $hotel->id
                ], $roomTypeData));
            }

            // Add phones
            foreach ($this->phones as $phoneData) {
                ManualPhone::create(array_merge([
                    'hotel_id' => $hotel->id
                ], $phoneData));
            }

            DB::commit();
            return $hotel->fresh();
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
}