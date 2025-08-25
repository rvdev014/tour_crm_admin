<?php

namespace App\Console\Commands;

use App\Enums\TourStatus;
use App\Enums\TourType;
use App\Models\City;
use App\Models\Company;
use App\Models\Country;
use App\Models\Tour;
use App\Models\TourDay;
use App\Models\TourDayExpense;
use App\Models\TourHotel;
use App\Models\TourPassenger;
use App\Models\TourRoomType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TourBuilderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tour:build {--copy=} {--interactive}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Build tours with a convenient builder pattern or copy existing tours with relations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $copyId = $this->option('copy');
        $interactive = $this->option('interactive');

        if ($copyId) {
            return $this->copyTour($copyId);
        }

        if ($interactive) {
            return $this->interactiveBuilder();
        }

        return $this->builderMode();
    }

    private function copyTour($tourId)
    {
        try {
            $originalTour = Tour::with([
                'days.expenses',
                'hotels',
                'roomTypes',
                'passengers',
                'expenses'
            ])->findOrFail($tourId);

            $this->info("Copying tour: {$originalTour->group_number}");

            DB::beginTransaction();

            // Copy tour with new group number
            $newTour = $originalTour->replicate();
            $newTour->group_number = null; // Will be auto-generated
            $newTour->save();

            // Copy days and their expenses
            foreach ($originalTour->days as $day) {
                $newDay = $day->replicate();
                $newDay->tour_id = $newTour->id;
                $newDay->save();

                foreach ($day->expenses as $expense) {
                    $newExpense = $expense->replicate();
                    $newExpense->tour_day_id = $newDay->id;
                    $newExpense->tour_id = $newTour->id;
                    $newExpense->save();
                }
            }

            // Copy hotels
            foreach ($originalTour->hotels as $hotel) {
                $newHotel = $hotel->replicate();
                $newHotel->tour_id = $newTour->id;
                $newHotel->save();
            }

            // Copy room types
            foreach ($originalTour->roomTypes as $roomType) {
                $newRoomType = $roomType->replicate();
                $newRoomType->tour_id = $newTour->id;
                $newRoomType->save();
            }

            // Copy passengers
            foreach ($originalTour->passengers as $passenger) {
                $newPassenger = $passenger->replicate();
                $newPassenger->tour_id = $newTour->id;
                $newPassenger->save();
            }

            // Copy direct expenses
            foreach ($originalTour->expenses as $expense) {
                $newExpense = $expense->replicate();
                $newExpense->tour_id = $newTour->id;
                $newExpense->save();
            }

            DB::commit();

            $this->info("✅ Tour copied successfully!");
            $this->line("Original: {$originalTour->group_number} → New: {$newTour->group_number}");
            $this->line("New Tour ID: {$newTour->id}");

            return 0;
        } catch (\Exception $e) {
            DB::rollback();
            $this->error("Error copying tour: {$e->getMessage()}");
            return 1;
        }
    }

    private function interactiveBuilder()
    {
        $this->info('🏗️ Interactive Tour Builder');
        $this->line('Follow the prompts to create a new tour.');

        $companies = Company::all();
        $countries = Country::all();
        $cities = City::all();
        $users = User::all();

        $builder = new TourBuilder();

        // Basic info
        $companyId = $this->choice('Select company:', $companies->pluck('name', 'id')->toArray());
        $builder->company($companyId);

        $countryId = $this->choice('Select country:', $countries->pluck('name', 'id')->toArray());
        $builder->country($countryId);

        $cityId = $this->choice('Select city:', $cities->pluck('name', 'id')->toArray());
        $builder->city($cityId);

        $createdById = $this->choice('Select creator:', $users->pluck('name', 'id')->toArray());
        $builder->createdBy($createdById);

        $startDate = $this->ask('Start date (Y-m-d H:i):');
        $builder->startDate($startDate);

        $endDate = $this->ask('End date (Y-m-d H:i):');
        $builder->endDate($endDate);

        $pax = (int) $this->ask('Number of passengers:', '1');
        $builder->pax($pax);

        $type = $this->choice('Tour type:', ['TPS', 'Corporate']);
        $builder->type($type === 'TPS' ? TourType::TPS : TourType::Corporate);

        $status = $this->choice('Status:', ['New', 'Confirmed', 'Completed', 'Cancelled']);
        $statusEnum = match($status) {
            'Confirmed' => TourStatus::Confirmed,
            'Completed' => TourStatus::Completed,
            'Cancelled' => TourStatus::Cancelled,
            default => TourStatus::New
        };
        $builder->status($statusEnum);

        if ($this->confirm('Add optional fields?')) {
            if ($comment = $this->ask('Comment (optional):')) {
                $builder->comment($comment);
            }
            if ($packageName = $this->ask('Package name (optional):')) {
                $builder->packageName($packageName);
            }
            if ($price = $this->ask('Price (optional):')) {
                $builder->price((float) $price);
            }
        }

        try {
            $tour = $builder->build();
            $this->info("✅ Tour created successfully!");
            $this->line("Group Number: {$tour->group_number}");
            $this->line("Tour ID: {$tour->id}");
            return 0;
        } catch (\Exception $e) {
            $this->error("Error creating tour: {$e->getMessage()}");
            return 1;
        }
    }

    private function builderMode()
    {
        $this->info('🏗️ Tour Builder Examples');
        $this->line('Here are some examples of how to use the TourBuilder in your code:');
        $this->newLine();

        $examples = [
            'Basic Tour:' => '
$tour = (new TourBuilder())
    ->company(1)
    ->country(1)
    ->city(1)
    ->createdBy(1)
    ->startDate("2025-09-01 10:00")
    ->endDate("2025-09-07 18:00")
    ->pax(15)
    ->type(TourType::TPS)
    ->status(TourStatus::New)
    ->build();',
            
            'Copy Existing Tour:' => '
php artisan tour:build --copy=123',
            
            'Interactive Mode:' => '
php artisan tour:build --interactive',
            
            'Advanced Tour with Relations:' => '
$tour = (new TourBuilder())
    ->company(1)
    ->country(1)
    ->city(1)
    ->createdBy(1)
    ->startDate("2025-09-01 10:00")
    ->endDate("2025-09-07 18:00")
    ->pax(25)
    ->type(TourType::Corporate)
    ->status(TourStatus::Confirmed)
    ->comment("Special corporate tour")
    ->packageName("Premium Package")
    ->price(1500.00)
    ->addDay("2025-09-01", "Arrival day")
    ->addDay("2025-09-02", "City tour")
    ->addHotel(5) // hotel_id
    ->addPassenger("John Doe", "john@example.com")
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

class TourBuilder
{
    private array $attributes = [];
    private array $days = [];
    private array $hotels = [];
    private array $passengers = [];

    public function company(int $companyId): self
    {
        $this->attributes['company_id'] = $companyId;
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

    public function createdBy(int $userId): self
    {
        $this->attributes['created_by'] = $userId;
        return $this;
    }

    public function startDate(string $date): self
    {
        $this->attributes['start_date'] = Carbon::parse($date);
        return $this;
    }

    public function endDate(string $date): self
    {
        $this->attributes['end_date'] = Carbon::parse($date);
        return $this;
    }

    public function pax(int $pax): self
    {
        $this->attributes['pax'] = $pax;
        return $this;
    }

    public function type(TourType $type): self
    {
        $this->attributes['type'] = $type;
        return $this;
    }

    public function status(TourStatus $status): self
    {
        $this->attributes['status'] = $status;
        return $this;
    }

    public function comment(string $comment): self
    {
        $this->attributes['comment'] = $comment;
        return $this;
    }

    public function packageName(string $name): self
    {
        $this->attributes['package_name'] = $name;
        return $this;
    }

    public function price(float $price): self
    {
        $this->attributes['price'] = $price;
        return $this;
    }

    public function addDay(string $date, string $description = null): self
    {
        $this->days[] = [
            'date' => Carbon::parse($date),
            'description' => $description
        ];
        return $this;
    }

    public function addHotel(int $hotelId): self
    {
        $this->hotels[] = $hotelId;
        return $this;
    }

    public function addPassenger(string $name, string $email = null): self
    {
        $this->passengers[] = [
            'name' => $name,
            'email' => $email
        ];
        return $this;
    }

    public function build(): Tour
    {
        DB::beginTransaction();
        try {
            $tour = Tour::create($this->attributes);

            // Add days
            foreach ($this->days as $dayData) {
                TourDay::create([
                    'tour_id' => $tour->id,
                    'date' => $dayData['date'],
                    'description' => $dayData['description']
                ]);
            }

            // Add hotels
            foreach ($this->hotels as $hotelId) {
                TourHotel::create([
                    'tour_id' => $tour->id,
                    'hotel_id' => $hotelId
                ]);
            }

            // Add passengers
            foreach ($this->passengers as $passengerData) {
                TourPassenger::create([
                    'tour_id' => $tour->id,
                    'name' => $passengerData['name'],
                    'email' => $passengerData['email']
                ]);
            }

            DB::commit();
            return $tour->fresh();
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }
}
