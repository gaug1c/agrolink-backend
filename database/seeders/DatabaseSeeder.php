<?php

/**
 * DatabaseSeeder.php
 * Seeder principal
 */
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
        ]);
    }
}

/**
 * UserSeeder.php
 * Créer des utilisateurs de test
 */
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // Admin
        User::create([
            'name' => 'Admin Agrolink',
            'email' => 'admin@agrolink.ga',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'phone' => '+24177123456',
            'city' => 'Libreville',
            'country' => 'Gabon',
            'region' => 'Estuaire',
            'is_verified' => true,
            'email_verified_at' => now(),
            'status' => 'active'
        ]);

        // Producteurs
        $producers = [
            [
                'name' => 'Ferme Bio Gabon',
                'email' => 'ferme.bio@agrolink.ga',
                'business_name' => 'Ferme Bio Gabon SARL',
                'city' => 'Libreville',
                'region' => 'Estuaire',
                'bio' => 'Spécialisée dans les produits bio et locaux depuis 2015',
                'phone' => '+24177234567'
            ],
            [
                'name' => 'Plantation Oyem',
                'email' => 'plantation@agrolink.ga',
                'business_name' => 'Plantation Oyem SA',
                'city' => 'Oyem',
                'region' => 'Woleu-Ntem',
                'bio' => 'Production de fruits et légumes frais',
                'phone' => '+24177345678'
            ],
            [
                'name' => 'Élevage Port-Gentil',
                'email' => 'elevage.pg@agrolink.ga',
                'business_name' => 'Élevage Port-Gentil',
                'city' => 'Port-Gentil',
                'region' => 'Ogooué-Maritime',
                'bio' => 'Élevage de volailles et production d\'œufs',
                'phone' => '+24177456789'
            ],
        ];

        foreach ($producers as $producer) {
            User::create([
                'name' => $producer['name'],
                'email' => $producer['email'],
                'password' => Hash::make('password123'),
                'role' => 'producer',
                'phone' => $producer['phone'],
                'city' => $producer['city'],
                'country' => 'Gabon',
                'region' => $producer['region'],
                'business_name' => $producer['business_name'],
                'bio' => $producer['bio'],
                'is_verified' => true,
                'email_verified_at' => now(),
                'status' => 'active'
            ]);
        }

        // Consommateurs
        User::create([
            'name' => 'Client Test',
            'email' => 'client@agrolink.ga',
            'password' => Hash::make('password123'),
            'role' => 'consumer',
            'phone' => '+24177567890',
            'city' => 'Libreville',
            'country' => 'Gabon',
            'region' => 'Estuaire',
            'address' => 'Quartier Nombakélé, Libreville',
            'is_verified' => true,
            'email_verified_at' => now(),
            'status' => 'active'
        ]);

        $this->command->info('Utilisateurs créés avec succès!');
    }
}

/**
 * CategorySeeder.php
 * Créer les catégories de produits
 */
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            [
                'name' => 'Fruits',
                'description' => 'Fruits frais locaux et importés',
                'icon' => '🍎',
                'children' => [
                    'Fruits tropicaux',
                    'Agrumes',
                    'Fruits de saison'
                ]
            ],
            [
                'name' => 'Légumes',
                'description' => 'Légumes frais du jardin',
                'icon' => '🥬',
                'children' => [
                    'Légumes feuilles',
                    'Légumes racines',
                    'Légumes tubercules'
                ]
            ],
            [
                'name' => 'Céréales',
                'description' => 'Riz, maïs, manioc et dérivés',
                'icon' => '🌾',
                'children' => [
                    'Riz',
                    'Maïs',
                    'Manioc',
                    'Farine'
                ]
            ],
            [
                'name' => 'Viandes & Poissons',
                'description' => 'Viandes fraîches et poissons',
                'icon' => '🥩',
                'children' => [
                    'Volailles',
                    'Poissons frais',
                    'Poissons fumés',
                    'Viande de brousse'
                ]
            ],
            [
                'name' => 'Produits laitiers',
                'description' => 'Lait, fromage, yaourt',
                'icon' => '🥛',
                'children' => [
                    'Lait',
                    'Fromage',
                    'Yaourt',
                    'Beurre'
                ]
            ],
            [
                'name' => 'Épices & Condiments',
                'description' => 'Épices, sauces et condiments locaux',
                'icon' => '🌶️',
                'children' => [
                    'Épices',
                    'Piments',
                    'Sauces',
                    'Aromates'
                ]
            ],
            [
                'name' => 'Huiles & Graisses',
                'description' => 'Huiles de palme, arachide et autres',
                'icon' => '🛢️',
                'children' => [
                    'Huile de palme',
                    'Huile d\'arachide',
                    'Huile de coco'
                ]
            ],
            [
                'name' => 'Boissons',
                'description' => 'Jus, boissons locales',
                'icon' => '🧃',
                'children' => [
                    'Jus de fruits',
                    'Vin de palme',
                    'Boissons traditionnelles'
                ]
            ]
        ];

        foreach ($categories as $categoryData) {
            $children = $categoryData['children'] ?? [];
            unset($categoryData['children']);

            $categoryData['slug'] = Str::slug($categoryData['name']);
            $categoryData['is_active'] = true;

            $category = Category::create($categoryData);

            // Créer les sous-catégories
            foreach ($children as $childName) {
                Category::create([
                    'name' => $childName,
                    'slug' => Str::slug($childName),
                    'parent_id' => $category->id,
                    'is_active' => true
                ]);
            }
        }

        $this->command->info('Catégories créées avec succès!');
    }
}

/**
 * ProductSeeder.php
 * Créer des produits de test
 */
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\User;
use App\Models\Category;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run()
    {
        $producers = User::where('role', 'producer')->get();
        
        if ($producers->isEmpty()) {
            $this->command->warn('Aucun producteur trouvé. Exécutez UserSeeder d\'abord.');
            return;
        }

        $products = [
            [
                'name' => 'Bananes plantain',
                'local_name' => 'Plantain',
                'description' => 'Bananes plantain fraîches, parfaites pour la cuisson',
                'category' => 'Fruits tropicaux',
                'price' => 1500,
                'unit' => 'kg',
                'stock' => 100,
                'type' => 'local',
                'city' => 'Libreville',
                'region' => 'Estuaire'
            ],
            [
                'name' => 'Manioc frais',
                'local_name' => 'Foufou',
                'description' => 'Manioc fraîchement récolté',
                'category' => 'Manioc',
                'price' => 1000,
                'discount_price' => 800,
                'unit' => 'kg',
                'stock' => 200,
                'type' => 'local',
                'city' => 'Oyem',
                'region' => 'Woleu-Ntem'
            ],
            [
                'name' => 'Tomates fraîches',
                'description' => 'Tomates bio cultivées localement',
                'category' => 'Légumes',
                'price' => 2000,
                'unit' => 'kg',
                'stock' => 50,
                'type' => 'bio',
                'city' => 'Libreville',
                'region' => 'Estuaire',
                'harvest_date' => now()->subDays(1)
            ],
            [
                'name' => 'Poulet fermier',
                'description' => 'Poulet élevé en plein air',
                'category' => 'Volailles',
                'price' => 5000,
                'unit' => 'kg',
                'stock' => 30,
                'type' => 'local',
                'city' => 'Port-Gentil',
                'region' => 'Ogooué-Maritime'
            ],
            [
                'name' => 'Ananas Victoria',
                'description' => 'Ananas sucré et juteux',
                'category' => 'Fruits tropicaux',
                'price' => 1500,
                'discount_price' => 1200,
                'unit' => 'piece',
                'stock' => 80,
                'type' => 'local',
                'city' => 'Libreville',
                'region' => 'Estuaire',
                'harvest_date' => now()
            ],
            [
                'name' => 'Feuilles de manioc',
                'local_name' => 'Saka-saka',
                'description' => 'Feuilles de manioc fraîches pour le saka-saka',
                'category' => 'Légumes feuilles',
                'price' => 500,
                'unit' => 'bunch',
                'stock' => 60,
                'type' => 'local',
                'city' => 'Oyem',
                'region' => 'Woleu-Ntem',
                'harvest_date' => now()
            ],
            [
                'name' => 'Huile de palme rouge',
                'description' => 'Huile de palme artisanale 100% naturelle',
                'category' => 'Huile de palme',
                'price' => 3000,
                'unit' => 'l',
                'stock' => 40,
                'type' => 'local',
                'city' => 'Libreville',
                'region' => 'Estuaire'
            ],
            [
                'name' => 'Piment frais',
                'description' => 'Piments locaux très piquants',
                'category' => 'Piments',
                'price' => 1000,
                'unit' => 'kg',
                'stock' => 25,
                'type' => 'local',
                'city' => 'Libreville',
                'region' => 'Estuaire'
            ]
        ];

        foreach ($products as $index => $productData) {
            $producer = $producers[$index % $producers->count()];
            
            $category = Category::where('name', $productData['category'])
                ->orWhere('name', 'LIKE', '%' . $productData['category'] . '%')
                ->first();

            if (!$category) {
                $category = Category::first();
            }

            Product::create([
                'producer_id' => $producer->id,
                'category_id' => $category->id,
                'name' => $productData['name'],
                'local_name' => $productData['local_name'] ?? null,
                'slug' => Str::slug($productData['name']),
                'description' => $productData['description'],
                'price' => $productData['price'],
                'discount_price' => $productData['discount_price'] ?? null,
                'unit' => $productData['unit'],
                'stock' => $productData['stock'],
                'type' => $productData['type'],
                'city' => $productData['city'],
                'region' => $productData['region'],
                'harvest_date' => $productData['harvest_date'] ?? null,
                'status' => 'active',
                'is_featured' => rand(0, 1) ? true : false,
                'views' => rand(0, 200),
                'rating' => rand(35, 50) / 10
            ]);
        }

        $this->command->info('Produits créés avec succès!');
    }
}