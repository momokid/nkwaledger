<?php

namespace Database\Seeders;

use App\Enums\SupplierAccountStatus;
use App\Models\AccountingPeriod;
use App\Models\CatalogProduct;
use App\Models\Community;
use App\Models\District;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmUnit;
use App\Models\Kiosk;
use App\Models\KioskProduct;
use App\Models\KioskProductPriceHistory;
use App\Models\LedgerAccount;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\Supplier;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use App\Models\ProduceListing;
use App\Services\ProduceListingService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

// every row this creates is tagged with an @demo.nkwaledger.test email, even on models
// (farmer, agent, vet, adviser, admin) where email is otherwise optional - it costs
// nothing (the column is already nullable everywhere but suppliers) and it is the one
// marker every seeded row shares, so a rerun can find and wipe exactly its own rows
// without guessing at a phone range or adding a migration for a boolean nobody else needs
class DemoDataSeeder extends Seeder
{
    private const DEMO_EMAIL_DOMAIN = 'demo.nkwaledger.test';

    private const PHONE_PREFIXES = ['024', '054', '055', '059', '020', '050', '026', '056', '027', '057'];

    private const FIRST_NAMES = [
        'Kwame', 'Kofi', 'Kwabena', 'Kwaku', 'Yaw', 'Kwadwo', 'Kwasi', 'Kojo', 'Fiifi', 'Ekow',
        'Yaa', 'Akosua', 'Abena', 'Akua', 'Ama', 'Efua', 'Esi', 'Adjoa', 'Adwoa', 'Afua',
        'Emmanuel', 'Samuel', 'Daniel', 'Joseph', 'Isaac', 'Michael', 'Prince', 'Richard', 'Solomon', 'Frank',
        'Abigail', 'Comfort', 'Gifty', 'Grace', 'Mercy', 'Vida', 'Rebecca', 'Rita', 'Linda', 'Patricia',
    ];

    private const LAST_NAMES = [
        'Mensah', 'Owusu', 'Osei', 'Boateng', 'Asante', 'Agyeman', 'Amoah', 'Appiah', 'Darko', 'Sarpong',
        'Ofori', 'Adjei', 'Antwi', 'Nkrumah', 'Frimpong', 'Yeboah', 'Gyasi', 'Baah', 'Danso', 'Acheampong',
        'Opoku', 'Wiredu', 'Kusi', 'Tetteh', 'Aidoo', 'Annan', 'Quaye', 'Lamptey', 'Sackey', 'Ankrah',
    ];

    private const COMMUNITY_NAMES = [
        'Nkawie', 'Mampong', 'Sunyani', 'Techiman', 'Berekum', 'Kintampo', 'Wenchi', 'Kumawu', 'Konongo', 'Ejura',
        'Bekwai', 'Obuasi', 'New Edubiase', 'Agona', 'Akropong', 'Aburi', 'Nsawam', 'Suhum', 'Koforidua', 'Nkawkaw',
        'Winneba', 'Swedru', 'Kasoa', 'Saltpond', 'Elmina', 'Cape Coast', 'Tarkwa', 'Axim', 'Wa', 'Bolgatanga',
        'Bawku', 'Navrongo', 'Yendi', 'Tamale', 'Savelugu', 'Salaga', 'Ho', 'Hohoe', 'Keta', 'Kpando',
    ];

    // admin/agent/vet/adviser are fixed; farmer/supplier/market buyer split the rest of a
    // 20-25 total, farmers kept largest since they're the app's primary user base
    private const ROLE_COUNTS = [
        'farmer' => 9,
        'admin' => 2,
        'supplier' => 3,
        'adviser' => 3,
        'vet' => 3,
        'agent' => 3,
        'market buyer' => 2,
    ];

    private int $sequence = 1;

    // one generated placeholder image per catalog product / farm type, reused across
    // every kiosk/listing selling that same product - a real image on disk, not a
    // path string pointing at nothing (see placeholderImagePath())
    private array $placeholderCache = [];

    public function run(): void
    {
        $this->wipe();

        $communities = $this->ensureCommunities();
        $period = $this->ensureBackfillPeriod();

        Role::firstOrCreate(['name' => 'market buyer', 'guard_name' => 'web']);

        $usersByRole = $this->seedUsers($communities);

        $this->seedFarms($usersByRole['farmer'], $communities, $period);
        $this->seedMarketplace($usersByRole['supplier'], $usersByRole['admin']);
        $this->seedProduceListings();
    }

    // never soft-deleted: a rerun must be able to reuse the same phone/email straight away,
    // and a hard delete is what actually fires the cascade rules the schema already relies on
    public function wipe(): void
    {
        $demoUserIds = User::where('email', 'like', '%@' . self::DEMO_EMAIL_DOMAIN)
            ->pluck('id');

        if ($demoUserIds->isEmpty()) {
            return;
        }

        $farmerProfileIds = FarmerProfile::withTrashed()->whereIn('user_id', $demoUserIds)->pluck('id');
        $supplierIds = Supplier::withTrashed()->whereIn('user_id', $demoUserIds)->pluck('id');
        $kioskIds = Kiosk::withTrashed()->whereIn('supplier_id', $supplierIds)->pluck('id');
        $catalogProductIds = CatalogProduct::withTrashed()
            ->whereIn('created_by', $demoUserIds)
            ->where('seeded', false)
            ->pluck('id');

        // transactions restrict-delete against farmer_profiles, so their journal rows go first
        $transactionIds = Transaction::whereIn('farmer_profile_id', $farmerProfileIds)->pluck('id');

        DB::table('journal_lines')->whereIn('journal_entry_id', function ($query) use ($transactionIds) {
            $query->select('id')->from('journal_entries')->whereIn('transaction_id', $transactionIds);
        })->delete();
        DB::table('journal_entries')->whereIn('transaction_id', $transactionIds)->delete();
        Transaction::whereIn('id', $transactionIds)->delete();

        // produce_sales/produce_listings restrict-delete against farm_unit_stocks and
        // farmer_profiles, so they go before FarmUnit::forceDelete() below cascades
        // into farm_unit_stocks - the same reason transactions went first, above
        $listingIds = ProduceListing::withTrashed()->whereIn('farmer_profile_id', $farmerProfileIds)->pluck('id');
        DB::table('produce_sales')->whereIn('produce_listing_id', $listingIds)->delete();
        DB::table('contact_requests')
            ->where('contactable_type', (new ProduceListing())->getMorphClass())
            ->whereIn('contactable_id', $listingIds)
            ->delete();
        ProduceListing::withTrashed()->whereIn('id', $listingIds)->forceDelete();

        // kiosk_products/price-histories/images and farm_unit_stock/movements cascade at the DB level
        Kiosk::withTrashed()->whereIn('id', $kioskIds)->forceDelete();
        CatalogProduct::withTrashed()->whereIn('id', $catalogProductIds)->forceDelete();
        Supplier::withTrashed()->whereIn('id', $supplierIds)->forceDelete();
        FarmUnit::withTrashed()->whereIn('farmer_profile_id', $farmerProfileIds)->forceDelete();
        FarmerProfile::withTrashed()->whereIn('id', $farmerProfileIds)->forceDelete();
        User::whereIn('id', $demoUserIds)->delete();
    }

    // reuses whatever communities already exist for this app, and tops up against real
    // districts (RegionDistrictSeeder's real 16 regions / 261 districts) if there are too
    // few to spread the demo farmers across sensibly - never invents a district
    private function ensureCommunities(): \Illuminate\Support\Collection
    {
        $districts = District::inRandomOrder()->limit(40)->get();

        foreach (self::COMMUNITY_NAMES as $i => $name) {
            $district = $districts[$i % $districts->count()];

            Community::firstOrCreate([
                'name' => $name,
                'district_id' => $district->id,
            ]);
        }

        return Community::whereIn('name', self::COMMUNITY_NAMES)->get();
    }

    // develop's real periods only cover August (closed) and Sep-Dec (open); this fills the
    // Mar-Jul gap so "last 6 months" has somewhere open to post into, without ever touching
    // (or reopening) a period that already exists for real reasons
    private function ensureBackfillPeriod(): AccountingPeriod
    {
        return AccountingPeriod::firstOrCreate(
            ['name' => 'Demo Backfill'],
            [
                'starts_on' => Carbon::parse('2026-03-01'),
                'ends_on' => Carbon::parse('2026-07-31'),
                'status' => AccountingPeriod::OPEN,
            ],
        );
    }

    /** @return array<string, \Illuminate\Support\Collection<int, User>> */
    private function seedUsers(\Illuminate\Support\Collection $communities): array
    {
        $usersByRole = [];

        foreach (self::ROLE_COUNTS as $role => $count) {
            $users = collect();

            for ($i = 0; $i < $count; $i++) {
                $user = User::create($this->personAttributes($role));
                $user->assignRole($role);
                $users->push($user);
            }

            $usersByRole[$role] = $users;
        }

        return $usersByRole;
    }

    private function personAttributes(string $role): array
    {
        $n = $this->sequence++;
        $slug = str_replace(' ', '', $role);

        return [
            'surname' => self::LAST_NAMES[$n % count(self::LAST_NAMES)],
            'first_name' => self::FIRST_NAMES[$n % count(self::FIRST_NAMES)],
            'other_name' => null,
            'phone' => $this->ghanaianPhone($n),
            'email' => "demo.{$slug}{$n}@" . self::DEMO_EMAIL_DOMAIN,
            'phone_verified_at' => now(),
            'email_verified_at' => now(),
            'password' => Hash::make('Password@123'),
            'is_active' => true,
        ];
    }

    private function ghanaianPhone(int $n): string
    {
        $prefix = self::PHONE_PREFIXES[$n % count(self::PHONE_PREFIXES)];

        return $prefix . str_pad((string) $n, 7, '0', STR_PAD_LEFT);
    }

    /** @param \Illuminate\Support\Collection<int, User> $farmers */
    private function seedFarms(\Illuminate\Support\Collection $farmers, \Illuminate\Support\Collection $communities, AccountingPeriod $period): void
    {
        $farmTypes = FarmType::with('category')->get();
        $templates = TransactionTemplate::where('is_active', true)
            ->whereNotIn('slug', ['correction'])
            ->get();
        $settlementAccounts = LedgerAccount::settlement()->get();
        $receivable = LedgerAccount::where('name', 'Accounts Receivable')->first();
        $payable = LedgerAccount::where('name', 'Accounts Payable')->first();
        $posting = app(PostingService::class);

        foreach ($farmers as $farmer) {
            $community = $communities->random();

            $profile = FarmerProfile::create([
                'user_id' => $farmer->id,
                'gender' => fake()->randomElement(['male', 'female']),
                'date_of_birth' => fake()->dateTimeBetween('-60 years', '-18 years'),
                'community_id' => $community->id,
                'onboarded_at' => now(),
                'is_active' => true,
            ]);

            $unitCount = fake()->numberBetween(1, 3);
            $units = collect();

            for ($i = 1; $i <= $unitCount; $i++) {
                $farmType = $farmTypes->random();
                // most units are approved so their transactions are settled, not provisional -
                // but a real minority stays pending, to exercise that path too
                $approved = fake()->boolean(80);

                $unit = FarmUnit::create([
                    'farmer_profile_id' => $profile->id,
                    'farm_type_id' => $farmType->id,
                    'community_id' => $community->id,
                    'name' => $farmType->name . ' Unit ' . $i,
                    'capacity' => fake()->numberBetween(20, 500),
                    'capacity_unit' => $farmType->category->name === 'Crop' ? 'acres' : 'head',
                    'created_by' => $farmer->id,
                    'approved_at' => $approved ? now() : null,
                    'approved_by' => $approved ? $farmer->id : null,
                    'is_active' => true,
                ]);

                // an opening batch so loss/sale templates always have live stock to draw from
                $opening = fake()->numberBetween(20, 300);
                $stock = \App\Models\FarmUnitStock::create([
                    'farm_unit_id' => $unit->id,
                    'source' => \App\Enums\StockSource::OpeningBalance,
                    'opening_quantity' => $opening,
                    'current_quantity' => $opening,
                    'unit_of_measure' => $unit->capacity_unit,
                    'acquisition_cost' => $opening * 20,
                    'started_on' => Carbon::parse('2026-03-05'),
                    'recorded_by' => $farmer->id,
                    'confirmed_at' => now(),
                    'confirmed_by' => $farmer->id,
                ]);
                $this->confirmOpeningMovement($stock, $farmer->id);

                $units->push($unit);
            }

            $eligibleTemplates = $templates->filter(
                fn(TransactionTemplate $t) => $t->farm_type_category_id === null
                    || $units->contains(fn(FarmUnit $u) => $u->farmType->category_id === $t->farm_type_category_id),
            );

            $transactionCount = fake()->numberBetween(4, 12);
            $postedProduceSale = false;
            $postedExpense = false;

            for ($i = 0; $i < $transactionCount; $i++) {
                $template = $eligibleTemplates->isEmpty() ? $templates->random() : $eligibleTemplates->random();
                $unit = $template->requires_farm_unit ? $units->random() : null;

                if ($template->requires_farm_unit && $units->contains(fn($u) => $u->farmType->category_id === $template->farm_type_category_id) === false && $template->farm_type_category_id !== null) {
                    continue;
                }

                $settlementAccountId = null;

                if ($template->settlement_side !== 'none') {
                    $onCredit = $template->allows_credit && fake()->boolean(20);

                    if ($onCredit) {
                        $settlementAccountId = $template->settlement_side === 'debit'
                            ? $receivable?->id
                            : $payable?->id;
                    }

                    $settlementAccountId ??= $settlementAccounts->random()->id;
                }

                $quantityLost = null;
                $quantitySold = null;
                $quantityPurchased = null;

                if ($template->transaction_type === Transaction::LOSS) {
                    $quantityLost = (string) fake()->numberBetween(1, 3);
                } elseif ($template->is_produce_sale) {
                    $quantitySold = (string) fake()->numberBetween(1, 10);
                } elseif ($template->is_stock_purchase) {
                    $quantityPurchased = (string) fake()->numberBetween(1, 10);
                }

                try {
                    $posting->post(new PostingRequest(
                        farmerProfileId: $profile->id,
                        transactionTemplateId: $template->id,
                        amount: (string) fake()->numberBetween(50, 2000),
                        settlementAccountId: $settlementAccountId,
                        transactionDate: $this->demoTransactionDate()->toDateString(),
                        farmUnitId: $unit?->id,
                        narration: null,
                        recordedBy: $farmer->id,
                        quantityLost: $quantityLost,
                        quantitySold: $quantitySold,
                        quantityPurchased: $quantityPurchased,
                        unitOfMeasure: $unit?->capacity_unit,
                    ));

                    if ($template->is_produce_sale) {
                        $postedProduceSale = true;
                    }

                    if ($template->transaction_type === Transaction::EXPENSE) {
                        $postedExpense = true;
                    }
                } catch (\App\Exceptions\Ledger\PostingFailed) {
                    // no live stock left to sell/lose from on this pass - skip, the farmer
                    // already has a realistic mix of transactions without forcing this one
                    continue;
                }
            }

            // the random mix above can (rarely) leave a farmer with no produce sale or no
            // expense at all - every demo farmer needs both represented, so force whichever
            // is missing with a template guaranteed to post
            if (! $postedProduceSale) {
                $this->forceProduceSale($posting, $profile, $units, $templates, $settlementAccounts, $farmer);
            }

            if (! $postedExpense) {
                $this->forceExpense($posting, $profile, $templates, $settlementAccounts, $farmer);
            }
        }
    }

    /** @param \Illuminate\Support\Collection<int, FarmUnit> $units */
    private function forceProduceSale(
        PostingService $posting,
        FarmerProfile $profile,
        \Illuminate\Support\Collection $units,
        \Illuminate\Support\Collection $templates,
        \Illuminate\Support\Collection $settlementAccounts,
        User $farmer,
    ): void {
        $template = $templates->firstWhere('slug', 'produce_sale');

        foreach ($units as $unit) {
            // the random loop above may have already sold/lost every batch down to 0 on
            // this unit - top it up so the forced sale always has live stock to draw from
            $topUp = \App\Models\FarmUnitStock::create([
                'farm_unit_id' => $unit->id,
                'source' => \App\Enums\StockSource::OpeningBalance,
                'opening_quantity' => 10,
                'current_quantity' => 10,
                'unit_of_measure' => $unit->capacity_unit,
                'acquisition_cost' => 100,
                'started_on' => Carbon::parse('2026-06-01'),
                'recorded_by' => $farmer->id,
                'confirmed_at' => now(),
                'confirmed_by' => $farmer->id,
            ]);
            $this->confirmOpeningMovement($topUp, $farmer->id);

            try {
                $posting->post(new PostingRequest(
                    farmerProfileId: $profile->id,
                    transactionTemplateId: $template->id,
                    amount: (string) fake()->numberBetween(50, 500),
                    settlementAccountId: $settlementAccounts->random()->id,
                    transactionDate: '2026-06-15',
                    farmUnitId: $unit->id,
                    narration: null,
                    recordedBy: $farmer->id,
                    quantitySold: '1',
                    unitOfMeasure: $unit->capacity_unit,
                ));

                return;
            } catch (\App\Exceptions\Ledger\PostingFailed) {
                continue;
            }
        }
    }

    // an opening/purchase movement is created unconfirmed by design (someone else checks
    // it before it counts) - the seeder plays that "someone else" so the batch is usable
    // right away instead of sitting at a current_quantity of 0
    private function confirmOpeningMovement(\App\Models\FarmUnitStock $stock, int $confirmedBy): void
    {
        $stock->movements()->first()?->update([
            'confirmed_at' => now(),
            'confirmed_by' => $confirmedBy,
        ]);
    }

    // labour_cost needs no farm unit and no live stock, so it always succeeds
    private function forceExpense(
        PostingService $posting,
        FarmerProfile $profile,
        \Illuminate\Support\Collection $templates,
        \Illuminate\Support\Collection $settlementAccounts,
        User $farmer,
    ): void {
        $template = $templates->firstWhere('slug', 'labour_cost');

        $posting->post(new PostingRequest(
            farmerProfileId: $profile->id,
            transactionTemplateId: $template->id,
            amount: (string) fake()->numberBetween(50, 500),
            settlementAccountId: $settlementAccounts->random()->id,
            transactionDate: '2026-06-15',
            narration: null,
            recordedBy: $farmer->id,
        ));
    }

    // spread across the last ~6 months, but never inside the real closed August 2026
    // period, and never in the future
    private function demoTransactionDate(): Carbon
    {
        do {
            $date = Carbon::parse('2026-03-01')->addDays(fake()->numberBetween(0, 206));
        } while ($date->isSameMonth(Carbon::parse('2026-08-15')) || $date->isFuture());

        return $date;
    }

    /**
     * @param \Illuminate\Support\Collection<int, User> $supplierUsers
     * @param \Illuminate\Support\Collection<int, User> $adminUsers
     */
    private function seedMarketplace(\Illuminate\Support\Collection $supplierUsers, \Illuminate\Support\Collection $adminUsers): void
    {
        $regions = \App\Models\Region::inRandomOrder()->limit(16)->get();
        $categories = ProductCategory::all();
        $units = ProductUnit::all();
        $admin = $adminUsers->first();

        // a shared pool of catalog products, some with a barcode - reused across several
        // kiosks' listings, the same way the real "first supplier scans it" rule works
        $catalogProducts = collect();
        for ($i = 0; $i < 15; $i++) {
            $catalogProducts->push(CatalogProduct::create([
                'name' => ucfirst(fake()->words(2, true)),
                'category_id' => $categories->random()->id,
                'unit_id' => $units->random()->id,
                'created_by' => $supplierUsers->first()->id,
                'barcode' => fake()->boolean(50) ? fake()->unique()->numerify('###############') : null,
                'barcode_type' => null,
            ]));
        }

        foreach ($supplierUsers as $index => $user) {
            $verificationRoll = fake()->numberBetween(1, 10);

            $supplier = Supplier::create([
                'user_id' => $user->id,
                'business_name' => fake()->company() . ' Farm Supplies',
                'email' => "demo.supplier{$index}@" . self::DEMO_EMAIL_DOMAIN,
                // mixed across the derived verification states: unverified / email+phone / id_verified
                'email_verified_at' => $verificationRoll >= 3 ? now() : null,
                'id_verified_at' => $verificationRoll >= 8 ? now() : null,
                'id_verified_by' => $verificationRoll >= 8 ? $admin->id : null,
            ]);

            // the first supplier gets a 2nd, admin-approved kiosk to exercise the cap-override path
            $kioskCount = $index === 0 ? 2 : 1;

            for ($k = 0; $k < $kioskCount; $k++) {
                $region = $regions->random();
                $district = $region->districts()->inRandomOrder()->first() ?? District::inRandomOrder()->first();

                $kiosk = Kiosk::create([
                    'supplier_id' => $supplier->id,
                    'name' => substr($supplier->business_name . ' Kiosk ' . ($k + 1), 0, 60),
                    'region_id' => $region->id,
                    'district_id' => $district->id,
                    'contact_phone' => $this->ghanaianPhone($this->sequence++),
                    'status' => \App\Enums\KioskStatus::Active,
                    'confirmed_at' => now(),
                    'requires_admin_approval' => $k > 0,
                    'admin_approved_at' => $k > 0 ? now() : null,
                    'admin_approved_by' => $k > 0 ? $admin->id : null,
                ]);

                $productCount = fake()->numberBetween(2, 6);
                $products = $catalogProducts->random($productCount);

                foreach ($products as $catalogProduct) {
                    $stale = fake()->boolean(15);
                    $nearExpiry = fake()->boolean(15);

                    $kioskProduct = KioskProduct::create([
                        'kiosk_id' => $kiosk->id,
                        'catalog_product_id' => $catalogProduct->id,
                        'price' => fake()->numberBetween(500, 50000),
                        'in_stock' => true,
                        'price_confirmed_at' => $stale ? now()->subDays(20) : now(),
                        'expiry_date' => $nearExpiry ? now()->addDays(3)->toDateString() : null,
                    ]);

                    // the same catalog product always gets the same generated image,
                    // the same way a real photo of the same item would look the same
                    // at every kiosk selling it
                    $kioskProduct->images()->create([
                        'path' => $this->placeholderImagePath("kiosk-products/catalog-{$catalogProduct->id}", $catalogProduct->name),
                    ]);

                    KioskProductPriceHistory::create([
                        'kiosk_product_id' => $kioskProduct->id,
                        'old_price' => null,
                        'new_price' => $kioskProduct->price,
                        'type' => \App\Enums\PriceHistoryType::Changed,
                        'changed_by' => $user->id,
                    ]);
                }
            }
        }
    }

    // real ProduceListing rows through the real service - not a raw insert - so the
    // stock-cap-under-lock logic this feature depends on is actually exercised, not
    // bypassed, on the way to populating the browse page with something to look at
    private function seedProduceListings(): void
    {
        $listings = app(ProduceListingService::class);

        $stocks = \App\Models\FarmUnitStock::query()
            ->whereHas('farmUnit.farmerProfile.user', fn($query) => $query->where('email', 'like', '%@' . self::DEMO_EMAIL_DOMAIN))
            ->whereNull('ended_on')
            ->where('current_quantity', '>', 0)
            ->with('farmUnit.farmerProfile.user', 'farmUnit.farmType.category')
            ->confirmed()
            ->inRandomOrder()
            ->limit(12)
            ->get();

        foreach ($stocks as $index => $stock) {
            $farmUnit = $stock->farmUnit;
            $farmer = $farmUnit->farmerProfile;
            $farmType = $farmUnit->farmType;
            $isCrop = $farmType->category?->name === 'Crop';

            // list roughly half of what is confirmed, never more than the batch has -
            // matches the stock-cap check the service itself enforces
            $quantity = max(1, round((float) $stock->current_quantity * fake()->randomFloat(2, 0.3, 0.6), 2));

            // a couple demonstrate the agent-posted-draft-awaiting-agreement path
            $postedByAgent = $index < 2;
            $postedBy = $postedByAgent
                ? User::role('agent')->where('email', 'like', '%@' . self::DEMO_EMAIL_DOMAIN)->inRandomOrder()->first() ?? $farmer->user
                : $farmer->user;

            try {
                // the agent-posted pair land as drafts automatically (create() checks
                // postedBy's own role), demonstrating that path waiting on the farmer
                // rather than the ordinary farmer-posts-their-own-listing path
                $listings->create(
                    $stock,
                    $farmer,
                    $postedBy,
                    $quantity,
                    $isCrop ? fake()->numberBetween(5, 21) : null,
                    $this->placeholderImagePath("produce-listings/{$stock->id}", $farmType->name),
                );
            } catch (\InvalidArgumentException) {
                // another seeded listing already claimed this batch's remaining room -
                // a real, expected outcome of the stock-cap check, not an error to hide
                continue;
            }
        }
    }

    // a stable, generated colour-block image with the label drawn on it - real bytes on
    // disk behind Storage::disk('public')->url(), not just a path string pointing at
    // nothing. Cached per key for this run so the same product/farm type always gets
    // the same image instead of a fresh one every time it is reused
    private function placeholderImagePath(string $key, string $label): string
    {
        if (isset($this->placeholderCache[$key])) {
            return $this->placeholderCache[$key];
        }

        $width = 400;
        $height = 300;
        $image = imagecreatetruecolor($width, $height);

        // a stable colour per label, not random, so the same product looks the same
        // on every reseed rather than flickering between runs
        $hash = crc32($label);
        $background = imagecolorallocate($image, 40 + ($hash & 0x7F), 40 + (($hash >> 8) & 0x7F), 40 + (($hash >> 16) & 0x7F));
        imagefill($image, 0, 0, $background);

        $textColor = imagecolorallocate($image, 255, 255, 255);
        $lines = explode(' ', $label, 2);
        imagestring($image, 5, 20, (int) ($height / 2) - 20, $lines[0], $textColor);

        if (isset($lines[1])) {
            imagestring($image, 5, 20, (int) ($height / 2), $lines[1], $textColor);
        }

        ob_start();
        imagejpeg($image, null, 80);
        $contents = ob_get_clean();
        imagedestroy($image);

        $path = "demo/{$key}.jpg";
        Storage::disk('public')->put($path, $contents);

        return $this->placeholderCache[$key] = $path;
    }
}
