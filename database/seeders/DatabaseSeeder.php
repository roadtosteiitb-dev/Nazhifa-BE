<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Default Users
        $ownerId = Str::uuid()->toString();
        $buyerId = Str::uuid()->toString();
        $adminId = Str::uuid()->toString();

        DB::statement("
            INSERT INTO users (id, full_name, email, password, user_type, created_at, updated_at)
            VALUES 
            (?, 'Pak Budi (Owner)', 'owner@example.com', ?, 'owner', NOW(), NOW()),
            (?, 'Siti Pembeli (Buyer)', 'buyer@example.com', ?, 'buyer', NOW(), NOW()),
            (?, 'Administrator', 'admin@example.com', ?, 'admin', NOW(), NOW())
            ON CONFLICT (email) DO UPDATE SET 
                password = EXCLUDED.password,
                full_name = EXCLUDED.full_name,
                user_type = EXCLUDED.user_type,
                updated_at = NOW()
        ", [
            $ownerId, Hash::make('password123'),
            $buyerId, Hash::make('password123'),
            $adminId, Hash::make('password123')
        ]);

        $owner = User::where('email', 'owner@example.com')->first();
        $actualOwnerId = $owner ? $owner->id : $ownerId;

        // Base S3 URL Helper
        $s3BaseUrl = 'https://cdn.titikhuni.s3.ap-southeast-1.amazonaws.com/property-images';

        // Helper function to build S3 Image URLs for cover and gallery
        $getImages = function (int $propsNum, int $count, string $ext) use ($s3BaseUrl): array {
            $images = [];
            for ($i = 1; $i <= $count; $i++) {
                $images[] = "{$s3BaseUrl}/props+{$propsNum}/{$i}.{$ext}";
            }
            return [
                'cover'   => "{$s3BaseUrl}/props+{$propsNum}/1.{$ext}",
                'gallery' => '{"' . implode('","', $images) . '"}',
            ];
        };

        // Build image arrays for all 12 properties according to user exact specs:
        $p1  = $getImages(1,  19, 'jpg');   // Props 1: 19 jpg
        $p2  = $getImages(2,  18, 'heic');  // Props 2: 18 heic
        $p3  = $getImages(3,  35, 'jpg');   // Props 3: 35 jpg
        $p4  = $getImages(4,  16, 'png');   // Props 4: 16 png
        $p5  = $getImages(5,  15, 'jpg');   // Props 5: 15 jpg
        $p6  = $getImages(6,  6,  'webp');  // Props 6: 6 webp
        $p7  = $getImages(7,  4,  'webp');  // Props 7: 4 webp
        $p8  = $getImages(8,  28, 'jpeg');  // Props 8: 28 jpeg
        $p9  = $getImages(9,  10, 'jpg');   // Props 9: 10 jpg
        $p10 = $getImages(10, 17, 'jpeg');  // Props 10: 17 jpeg
        $p11 = $getImages(11, 19, 'webp');  // Props 11: 19 webp
        $p12 = $getImages(12, 10, 'jpeg');  // Props 12: 10 jpeg

        // Clear existing lands before seeding to prevent duplicates
        DB::table('lands')->delete();

        // 2. 12 Real Property Listings with AWS S3 Images & Facilities
        $lands = [
            [
                'id'            => Str::uuid()->toString(),
                'name'          => 'Rumah Mewah 2 Lantai Mlati',
                'location'      => 'Mlati, Sleman, Yogyakarta',
                'price'         => 1750000000,
                'is_for_sale'   => true,
                'type'          => 'house',
                'status'        => 'Approved',
                'owner_id'      => $actualOwnerId,
                'description'   => 'Ruang Tamu, Ruang Keluarga, Dapur Luas, Listrik 2200W, Air Sumur Bor, Carport 2 Mobil, Balkon, Beranda, Kitchen Set dengan Kabinet, Taman Depan & Samping.',
                'image'         => $p1['cover'],
                'images'        => $p1['gallery'],
                'bedrooms'      => 3,
                'bathrooms'     => 2,
                'electricity'   => 2200,
                'land_area'     => '160',
                'building_area' => '170',
                'certificate'   => 'SHM',
                'garage'        => 'Carport 2 Mobil',
                'facilities'    => '{"Ruang Tamu","Ruang Keluarga","Dapur Luas","Sumur Bor","Balkon","Beranda","Kitchen Set","Taman Depan","Taman Samping","Carport 2 Mobil"}',
                'lat'           => -7.741122,
                'lng'           => 110.365759,
            ],
            [
                'id'            => Str::uuid()->toString(),
                'name'          => 'Cluster Perumahan Minimalis Sleman',
                'location'      => 'Sleman, Yogyakarta',
                'price'         => 785000000,
                'is_for_sale'   => true,
                'type'          => 'house',
                'status'        => 'Approved',
                'owner_id'      => $actualOwnerId,
                'description'   => 'Cluster perumahan hadap selatan, IMB/PBG lengkap, KPR bisa. Dinding bata merah, lantai granit 60x60, plafon baja ringan gypsum, kusen aluminium black.',
                'image'         => $p2['cover'],
                'images'        => $p2['gallery'],
                'bedrooms'      => 3,
                'bathrooms'     => 2,
                'floors'        => 1,
                'electricity'   => 1300,
                'land_area'     => '98',
                'building_area' => '65',
                'certificate'   => 'SHM',
                'garage'        => 'Parkir 1 Mobil',
                'facilities'    => '{"Ruang Tamu","Ruang Keluarga","Dapur","Sumur Bor","Tempat Cuci Jemur","Cluster Perumahan","Jalan Paving 5m","Carport 1 Mobil"}',
                'lat'           => -7.728240,
                'lng'           => 110.332863,
            ],
            [
                'id'            => Str::uuid()->toString(),
                'name'          => 'Rumah 2 Lantai Full Furnished Maguwo',
                'location'      => 'Wedomartani, Ngemplak, Sleman, DI Yogyakarta',
                'price'         => 2789000000,
                'is_for_sale'   => true,
                'type'          => 'house',
                'status'        => 'Approved',
                'owner_id'      => $actualOwnerId,
                'description'   => 'Perum Bumi Sentosa 2 Maguwo. Siap huni full furnished: AC, Smart TV, Water Heater, Kulkas, Kompor Tanam, Kitchen Set. Akses jalan 5m one gate system.',
                'image'         => $p3['cover'],
                'images'        => $p3['gallery'],
                'bedrooms'      => 4,
                'bathrooms'     => 4,
                'floors'        => 2,
                'electricity'   => 5500,
                'furnished'     => 'furnished',
                'land_area'     => '125',
                'building_area' => '183',
                'certificate'   => 'SHM',
                'garage'        => 'Garasi 2 Mobil',
                'facilities'    => '{"Full Furnished","AC","Smart TV","Water Heater","Kulkas","Kompor Tanam","Cooker Hood","Kitchen Set","One Gate System","Garasi 2 Mobil"}',
                'lat'           => -7.735083745091841,
                'lng'           => 110.42013084896006,
            ],
            [
                'id'            => Str::uuid()->toString(),
                'name'          => 'Rumah Asri Jalan Paving 6m Sleman',
                'location'      => 'Sleman, Yogyakarta',
                'price'         => 1625000000,
                'is_for_sale'   => true,
                'type'          => 'house',
                'status'        => 'Approved',
                'owner_id'      => $actualOwnerId,
                'description'   => '3 Kamar Tidur, 2 Kamar Mandi, Ruang Keluarga, Dapur, Ruang Tamu, Ruang Makan, Teras, Taman, Ruang Cuci & Santai, Balkon, Akses Jalan Paving 6m.',
                'image'         => $p4['cover'],
                'images'        => $p4['gallery'],
                'bedrooms'      => 3,
                'bathrooms'     => 2,
                'electricity'   => 2200,
                'land_area'     => '102',
                'building_area' => '100',
                'garage'        => 'Carport 2 Mobil',
                'facilities'    => '{"Ruang Tamu","Ruang Keluarga","Ruang Makan","Dapur","Teras","Taman","Ruang Cuci","Ruang Santai","Balkon","Air Sumur","Jalan Paving 6m","Carport 2 Mobil"}',
                'lat'           => -7.777792111148703,
                'lng'           => 110.3150578,
            ],
            [
                'id'            => Str::uuid()->toString(),
                'name'          => 'Rumah Sewa Perum Citrasun Garden',
                'location'      => 'Perum Citrasun Garden, Sleman, Yogyakarta',
                'price'         => 202000000,
                'is_for_sale'   => false,
                'type'          => 'house',
                'status'        => 'Approved',
                'owner_id'      => $actualOwnerId,
                'description'   => 'Disewakan rumah 2 lantai luas 13x13m. 5 Kamar Tidur, 5 Kamar Mandi, 6 AC, Listrik 4400W, Air PAM, Garasi 2 + Carport 2.',
                'image'         => $p5['cover'],
                'images'        => $p5['gallery'],
                'bedrooms'      => 5,
                'bathrooms'     => 5,
                'floors'        => 2,
                'electricity'   => 4400,
                'land_area'     => '315',
                'building_area' => '345',
                'garage'        => 'Garasi 2 + Carport 2',
                'facilities'    => '{"6 Unit AC","Air PAM","Ruang Keluarga","Dapur","Garasi 2 Mobil","Carport 2 Mobil","Kompleks Perumahan Elite"}',
                'lat'           => -7.78132094809866,
                'lng'           => 110.4484157554292,
            ],
            [
                'id'            => Str::uuid()->toString(),
                'name'          => 'Apartemen Minimalis Lantai 5 Sleman',
                'location'      => 'Sleman, Yogyakarta',
                'price'         => 66000000,
                'is_for_sale'   => false,
                'type'          => 'apartment',
                'status'        => 'Approved',
                'owner_id'      => $actualOwnerId,
                'description'   => 'Disewakan unit apartemen lantai 5 dengan fasilitas AC, Garasi, Garden, dan Swimming Pool.',
                'image'         => $p6['cover'],
                'images'        => $p6['gallery'],
                'bedrooms'      => 1,
                'bathrooms'     => 1,
                'unit_floor'    => 5,
                'unit_type'     => 'Studio',
                'building_area' => '34',
                'garage'        => 'Ada',
                'facilities'    => '{"AC","Kolam Renang / Swimming Pool","Taman / Garden","Garasi / Parkir","Security 24 Jam"}',
                'lat'           => -7.739066462230481,
                'lng'           => 110.37729212883576,
            ],
            [
                'id'            => Str::uuid()->toString(),
                'name'          => 'Apartemen Studio Taman Melati UGM',
                'location'      => 'Mlati, Sleman, Yogyakarta (Dekat UGM)',
                'price'         => 45000000,
                'is_for_sale'   => false,
                'type'          => 'apartment',
                'status'        => 'Approved',
                'owner_id'      => $actualOwnerId,
                'description'   => 'AA005 Disewakan Studio Taman Melati dekat Teknik & MM UGM. Hadap Selatan view UGM. Interior premium, Smart TV 43" 4K, Water Heater, Kasur Airland, Kulkas 2 Pintu. Fasilitas kolam renang, gym, coworking space.',
                'image'         => $p7['cover'],
                'images'        => $p7['gallery'],
                'bedrooms'      => 1,
                'bathrooms'     => 1,
                'unit_floor'    => 8,
                'unit_type'     => 'Studio',
                'furnished'     => 'furnished',
                'electricity'   => 1300,
                'building_area' => '22',
                'facilities'    => '{"Full Furnished","Smart TV 43 Inch 4K","Water Heater","Kasur Airland","Kulkas 2 Pintu","Dispenser","Balkon View UGM","Kolam Renang","Gym / Fitness Center","Co-Working Space","Minimarket & Laundry"}',
                'lat'           => -7.762060487831903,
                'lng'           => 110.37238297116421,
            ],
            [
                'id'            => Str::uuid()->toString(),
                'name'          => 'Rumah Sewa Taman Palagan Asri 3',
                'location'      => 'Palagan, Sleman, Yogyakarta',
                'price'         => 147000000,
                'is_for_sale'   => false,
                'type'          => 'house',
                'status'        => 'Approved',
                'owner_id'      => $actualOwnerId,
                'description'   => 'Disewakan rumah 2 lantai di Taman Palagan Asri 3. 5 Kamar Tidur, 3 AC, Listrik 3500W, Air Sumur, Garasi 1 + Carport 2.',
                'image'         => $p8['cover'],
                'images'        => $p8['gallery'],
                'bedrooms'      => 5,
                'bathrooms'     => 3,
                'floors'        => 2,
                'electricity'   => 3500,
                'land_area'     => '245',
                'building_area' => '281',
                'garage'        => 'Garasi 1 + Carport 2',
                'facilities'    => '{"3 Unit AC","Air Sumur","Ruang Tamu","Dapur","Garasi 1 Mobil","Carport 2 Mobil","Lingkungan Perumahan"}',
                'lat'           => -7.713360812475041,
                'lng'           => 110.38569977116424,
            ],
            [
                'id'            => Str::uuid()->toString(),
                'name'          => 'Villa Merapi GreenHills Full Furnished',
                'location'      => 'Pakem, Sleman, Yogyakarta',
                'price'         => 32000000,
                'is_for_sale'   => false,
                'type'          => 'house',
                'status'        => 'Approved',
                'owner_id'      => $actualOwnerId,
                'description'   => 'Full Furnished 1BR House at Villa Merapi GreenHills Pakem Sleman. Akomodasi 2 lantai cocok untuk 2 orang, 1 AC, Listrik 1300W.',
                'image'         => $p9['cover'],
                'images'        => $p9['gallery'],
                'bedrooms'      => 1,
                'bathrooms'     => 1,
                'floors'        => 2,
                'electricity'   => 1300,
                'furnished'     => 'furnished',
                'land_area'     => '35',
                'building_area' => '35',
                'garage'        => 'Carport 1',
                'facilities'    => '{"Full Furnished","1 Unit AC","Ruang Tamu","Dapur","Air Sumur","Carport 1 Mobil","Lingkungan Villa Hijau"}',
                'lat'           => -7.677669697967648,
                'lng'           => 110.40158827116423,
            ],
            [
                'id'            => Str::uuid()->toString(),
                'name'          => 'Villa Asri Wisata Ledok Sambi Kaliurang',
                'location'      => 'Umbulharjo, Cangkringan, Sleman, Yogyakarta',
                'price'         => 2500000000,
                'is_for_sale'   => true,
                'type'          => 'house',
                'status'        => 'Approved',
                'owner_id'      => $actualOwnerId,
                'description'   => 'Villa view Gunung Merapi dalam wisata Ledok Sambi Jalan Kaliurang. LT 3145m², LB 900m², lebar depan 30m, hadap selatan, 9 kamar tidur, air mata air alam.',
                'image'         => $p10['cover'],
                'images'        => $p10['gallery'],
                'bedrooms'      => 9,
                'bathrooms'     => 2,
                'electricity'   => 4400,
                'land_area'     => '3145',
                'building_area' => '900',
                'facilities'    => '{"Semi Furnished","Mata Air Alam","View Gunung Merapi","Halaman Sangat Luas","Akses Kawasan Wisata Ledok Sambi","Lebar Depan 30m"}',
                'lat'           => -7.630462541793105,
                'lng'           => 110.45374389814965,
            ],
            [
                'id'            => Str::uuid()->toString(),
                'name'          => 'Villa Joglo Merapi View Turi Sleman',
                'location'      => 'Girikerto, Turi, Sleman, DI Yogyakarta',
                'price'         => 3000000000,
                'is_for_sale'   => true,
                'type'          => 'house',
                'status'        => 'Approved',
                'owner_id'      => $actualOwnerId,
                'description'   => 'Dijual cepat Villa Joglo desain khas Jawa di Turi Sleman. View Merapi, taman luas, kolam renang, solar panel, full furnished, SHM & IMB.',
                'image'         => $p11['cover'],
                'images'        => $p11['gallery'],
                'bedrooms'      => 5,
                'bathrooms'     => 5,
                'floors'        => 1,
                'electricity'   => 2200,
                'furnished'     => 'furnished',
                'land_area'     => '1019',
                'building_area' => '350',
                'certificate'   => 'SHM',
                'facilities'    => '{"Full Furnished","Kolam Renang","Bangunan Joglo Jawa","Taman Luas","Solar Panel / Panel Surya","Air Sumur","View Gunung Merapi","Pagar Tembok Keliling"}',
                'lat'           => -7.602083799238141,
                'lng'           => 110.40632027116422,
            ],
            [
                'id'            => Str::uuid()->toString(),
                'name'          => 'Rumah Tinggal & Paviliun Halaman Luas',
                'location'      => 'Sleman, Yogyakarta',
                'price'         => 8000000000,
                'is_for_sale'   => true,
                'type'          => 'house',
                'status'        => 'Approved',
                'owner_id'      => $actualOwnerId,
                'description'   => 'Rumah tinggal halaman sangat luas LT 1225m², LB 700m², lebar depan 22m. Dilengkapi 5 kamar tidur + 2 paviliun, 9 kamar mandi, carport, furnish.',
                'image'         => $p12['cover'],
                'images'        => $p12['gallery'],
                'bedrooms'      => 5,
                'bathrooms'     => 9,
                'floors'        => 1,
                'electricity'   => 5500,
                'furnished'     => 'furnished',
                'land_area'     => '1225',
                'building_area' => '700',
                'garage'        => 'Carport Ada',
                'facilities'    => '{"Full Furnished","2 Unit Paviliun Terpisah","Halaman & Taman Sangat Luas","Dapur Utama","Air Sumur","Carport & Parkir Luas"}',
                'lat'           => -7.617756264674238,
                'lng'           => 110.38152326399238,
            ],
        ];

        foreach ($lands as $land) {
            $lat = $land['lat'];
            $lng = $land['lng'];
            unset($land['lat'], $land['lng']);

            DB::statement("
                INSERT INTO lands (
                    id, name, location, price, is_for_sale, type, status, owner_id, 
                    description, image, images, bedrooms, bathrooms, floors, electricity, 
                    furnished, land_area, building_area, certificate, garage, facilities, geom, created_at, updated_at
                )
                VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, ?, ?, ?, 
                    ?, ?, ?, ?, ?, ?, ST_SetSRID(ST_MakePoint(?, ?), 4326), NOW(), NOW()
                )
                ON CONFLICT (id) DO NOTHING
            ", [
                $land['id'], $land['name'], $land['location'], $land['price'],
                $land['is_for_sale'], $land['type'], $land['status'], $land['owner_id'],
                $land['description'], $land['image'], $land['images'] ?? null,
                $land['bedrooms'] ?? null, $land['bathrooms'] ?? null, $land['floors'] ?? null, $land['electricity'] ?? null,
                $land['furnished'] ?? null, $land['land_area'] ?? null, $land['building_area'] ?? null,
                $land['certificate'] ?? null, $land['garage'] ?? null, $land['facilities'] ?? null,
                $lng, $lat
            ]);
        }
    }
}





