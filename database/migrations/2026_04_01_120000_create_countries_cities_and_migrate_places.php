<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->char('iso_alpha2', 2)->unique();
            $table->timestamps();
        });

        Schema::create('country_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('name');
            $table->unique(['country_id', 'locale']);
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('city_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_id')->constrained('cities')->cascadeOnDelete();
            $table->string('locale', 12);
            $table->string('name');
            $table->unique(['city_id', 'locale']);
        });

        $this->seedCountries();
        $this->seedPolishCities();

        if (Schema::hasTable('places') && Schema::hasColumn('places', 'city')) {
            Schema::table('places', function (Blueprint $table) {
                $table->foreignId('country_id')->nullable()->after('name')->constrained('countries')->nullOnDelete();
                $table->foreignId('city_id')->nullable()->after('country_id')->constrained('cities')->nullOnDelete();
            });

            $this->migratePlaceStringsToFks();

            Schema::table('places', function (Blueprint $table) {
                if (Schema::hasColumn('places', 'country')) {
                    $table->dropColumn('country');
                }
                if (Schema::hasColumn('places', 'city')) {
                    $table->dropColumn('city');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('places')) {
            Schema::table('places', function (Blueprint $table) {
                if (Schema::hasColumn('places', 'city_id')) {
                    $table->dropForeign(['city_id']);
                    $table->dropColumn('city_id');
                }
                if (Schema::hasColumn('places', 'country_id')) {
                    $table->dropForeign(['country_id']);
                    $table->dropColumn('country_id');
                }
            });

            Schema::table('places', function (Blueprint $table) {
                if (! Schema::hasColumn('places', 'city')) {
                    $table->string('city')->nullable()->after('name');
                }
                if (! Schema::hasColumn('places', 'country')) {
                    $table->string('country')->nullable()->after('city');
                }
            });
        }

        Schema::dropIfExists('city_translations');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('country_translations');
        Schema::dropIfExists('countries');
    }

    private function seedCountries(): void
    {
        $rows = [
            ['iso' => 'PL', 'en' => 'Poland', 'pl' => 'Polska'],
            ['iso' => 'DE', 'en' => 'Germany', 'pl' => 'Niemcy'],
            ['iso' => 'CZ', 'en' => 'Czechia', 'pl' => 'Czechy'],
            ['iso' => 'SK', 'en' => 'Slovakia', 'pl' => 'Słowacja'],
            ['iso' => 'UA', 'en' => 'Ukraine', 'pl' => 'Ukraina'],
            ['iso' => 'GB', 'en' => 'United Kingdom', 'pl' => 'Wielka Brytania'],
            ['iso' => 'US', 'en' => 'United States', 'pl' => 'Stany Zjednoczone'],
            ['iso' => 'FR', 'en' => 'France', 'pl' => 'Francja'],
            ['iso' => 'NL', 'en' => 'Netherlands', 'pl' => 'Holandia'],
            ['iso' => 'BE', 'en' => 'Belgium', 'pl' => 'Belgia'],
            ['iso' => 'AT', 'en' => 'Austria', 'pl' => 'Austria'],
            ['iso' => 'SE', 'en' => 'Sweden', 'pl' => 'Szwecja'],
            ['iso' => 'NO', 'en' => 'Norway', 'pl' => 'Norwegia'],
            ['iso' => 'DK', 'en' => 'Denmark', 'pl' => 'Dania'],
            ['iso' => 'FI', 'en' => 'Finland', 'pl' => 'Finlandia'],
            ['iso' => 'EE', 'en' => 'Estonia', 'pl' => 'Estonia'],
            ['iso' => 'LV', 'en' => 'Latvia', 'pl' => 'Łotwa'],
            ['iso' => 'LT', 'en' => 'Lithuania', 'pl' => 'Litwa'],
            ['iso' => 'RO', 'en' => 'Romania', 'pl' => 'Rumunia'],
            ['iso' => 'HU', 'en' => 'Hungary', 'pl' => 'Węgry'],
        ];

        foreach ($rows as $row) {
            $id = DB::table('countries')->insertGetId([
                'iso_alpha2' => $row['iso'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('country_translations')->insert([
                ['country_id' => $id, 'locale' => 'en', 'name' => $row['en']],
                ['country_id' => $id, 'locale' => 'pl', 'name' => $row['pl']],
            ]);
        }
    }

    private function seedPolishCities(): void
    {
        $plId = DB::table('countries')->where('iso_alpha2', 'PL')->value('id');
        if (! $plId) {
            return;
        }

        $cities = [
            ['en' => 'Warsaw', 'pl' => 'Warszawa'],
            ['en' => 'Kraków', 'pl' => 'Kraków'],
            ['en' => 'Wrocław', 'pl' => 'Wrocław'],
            ['en' => 'Poznań', 'pl' => 'Poznań'],
            ['en' => 'Gdańsk', 'pl' => 'Gdańsk'],
            ['en' => 'Szczecin', 'pl' => 'Szczecin'],
            ['en' => 'Bydgoszcz', 'pl' => 'Bydgoszcz'],
            ['en' => 'Lublin', 'pl' => 'Lublin'],
            ['en' => 'Katowice', 'pl' => 'Katowice'],
            ['en' => 'Białystok', 'pl' => 'Białystok'],
            ['en' => 'Gdynia', 'pl' => 'Gdynia'],
            ['en' => 'Częstochowa', 'pl' => 'Częstochowa'],
            ['en' => 'Radom', 'pl' => 'Radom'],
            ['en' => 'Sosnowiec', 'pl' => 'Sosnowiec'],
            ['en' => 'Toruń', 'pl' => 'Toruń'],
            ['en' => 'Kielce', 'pl' => 'Kielce'],
            ['en' => 'Gliwice', 'pl' => 'Gliwice'],
            ['en' => 'Zabrze', 'pl' => 'Zabrze'],
            ['en' => 'Olsztyn', 'pl' => 'Olsztyn'],
            ['en' => 'Bielsko-Biała', 'pl' => 'Bielsko-Biała'],
            ['en' => 'Bytom', 'pl' => 'Bytom'],
            ['en' => 'Rzeszów', 'pl' => 'Rzeszów'],
            ['en' => 'Ruda Śląska', 'pl' => 'Ruda Śląska'],
            ['en' => 'Rybnik', 'pl' => 'Rybnik'],
            ['en' => 'Tychy', 'pl' => 'Tychy'],
            ['en' => 'Opole', 'pl' => 'Opole'],
            ['en' => 'Elbląg', 'pl' => 'Elbląg'],
            ['en' => 'Gorzów Wielkopolski', 'pl' => 'Gorzów Wielkopolski'],
            ['en' => 'Włocławek', 'pl' => 'Włocławek'],
            ['en' => 'Tarnów', 'pl' => 'Tarnów'],
            ['en' => 'Chorzów', 'pl' => 'Chorzów'],
            ['en' => 'Kalisz', 'pl' => 'Kalisz'],
            ['en' => 'Koszalin', 'pl' => 'Koszalin'],
            ['en' => 'Legnica', 'pl' => 'Legnica'],
            ['en' => 'Grudziądz', 'pl' => 'Grudziądz'],
            ['en' => 'Słupsk', 'pl' => 'Słupsk'],
            ['en' => 'Jaworzno', 'pl' => 'Jaworzno'],
            ['en' => 'Jastrzębie-Zdrój', 'pl' => 'Jastrzębie-Zdrój'],
            ['en' => 'Jelenia Góra', 'pl' => 'Jelenia Góra'],
            ['en' => 'Nowy Sącz', 'pl' => 'Nowy Sącz'],
            ['en' => 'Konin', 'pl' => 'Konin'],
            ['en' => 'Piotrków Trybunalski', 'pl' => 'Piotrków Trybunalski'],
            ['en' => 'Lubin', 'pl' => 'Lubin'],
            ['en' => 'Ostrołęka', 'pl' => 'Ostrołęka'],
            ['en' => 'Stargard', 'pl' => 'Stargard'],
            ['en' => 'Mysłowice', 'pl' => 'Mysłowice'],
            ['en' => 'Płock', 'pl' => 'Płock'],
            ['en' => 'Łódź', 'pl' => 'Łódź'],
        ];

        foreach ($cities as $names) {
            $cityId = DB::table('cities')->insertGetId([
                'country_id' => $plId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('city_translations')->insert([
                ['city_id' => $cityId, 'locale' => 'en', 'name' => $names['en']],
                ['city_id' => $cityId, 'locale' => 'pl', 'name' => $names['pl']],
            ]);
        }
    }

    private function migratePlaceStringsToFks(): void
    {
        $plId = DB::table('countries')->where('iso_alpha2', 'PL')->value('id');

        $countryNameMap = [
            'poland' => 'PL', 'polska' => 'PL', 'pl' => 'PL',
            'germany' => 'DE', 'niemcy' => 'DE', 'deutschland' => 'DE',
            'czechia' => 'CZ', 'czech republic' => 'CZ',
            'united kingdom' => 'GB', 'uk' => 'GB', 'england' => 'GB',
            'united states' => 'US', 'usa' => 'US',
            'france' => 'FR', 'francja' => 'FR',
            'ukraine' => 'UA', 'ukraina' => 'UA',
        ];

        $places = DB::table('places')->select('id', 'city', 'country')->get();

        foreach ($places as $place) {
            $countryId = null;
            $cityId = null;

            $countryStr = $place->country ? mb_strtolower(trim($place->country)) : '';
            $cityStr = $place->city ? trim($place->city) : '';

            if ($countryStr !== '') {
                $iso = $countryNameMap[$countryStr] ?? null;
                if ($iso) {
                    $countryId = DB::table('countries')->where('iso_alpha2', $iso)->value('id');
                }
                if (! $countryId) {
                    $countryId = DB::table('country_translations')
                        ->whereRaw('LOWER(name) = ?', [$countryStr])
                        ->value('country_id');
                }
            }

            // If we have a city name but could not resolve country, assume Poland (app default region).
            if (! $countryId && $cityStr !== '' && $plId) {
                $countryId = $plId;
            }

            if ($countryId && $cityStr !== '') {
                $cityId = DB::table('city_translations')
                    ->join('cities', 'cities.id', '=', 'city_translations.city_id')
                    ->where('cities.country_id', $countryId)
                    ->whereRaw('LOWER(city_translations.name) = ?', [mb_strtolower($cityStr)])
                    ->value('cities.id');

                if (! $cityId) {
                    $cityId = DB::table('cities')->insertGetId([
                        'country_id' => $countryId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    DB::table('city_translations')->insert([
                        ['city_id' => $cityId, 'locale' => 'en', 'name' => $cityStr],
                        ['city_id' => $cityId, 'locale' => 'pl', 'name' => $cityStr],
                    ]);
                }
            }

            DB::table('places')->where('id', $place->id)->update([
                'country_id' => $countryId,
                'city_id' => $cityId,
            ]);
        }
    }
};
