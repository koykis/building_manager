<?php

namespace Database\Seeders;

use App\Models\Apartment;
use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Apartment::firstOrCreate(['label' => 'Α1'], ['alias' => 'A1', 'floor' => 1, 'car_lift' => 1, 'active' => true]);
        Apartment::firstOrCreate(['label' => 'Α2'], ['alias' => 'A2', 'floor' => 1, 'car_lift' => 1, 'active' => true]);
        Apartment::firstOrCreate(['label' => 'Β1'], ['alias' => 'B1', 'floor' => 2, 'car_lift' => 1, 'active' => true]);
        Apartment::firstOrCreate(['label' => 'Β2'], ['alias' => 'B2', 'floor' => 2, 'car_lift' => 0, 'active' => true]);
        Apartment::firstOrCreate(['label' => 'Γ1'], ['alias' => 'C1', 'floor' => 3, 'car_lift' => 0, 'active' => true]);
        Apartment::firstOrCreate(['label' => 'Δ1'], ['alias' => 'D1', 'floor' => 4, 'car_lift' => 0, 'active' => true]);
        Apartment::firstOrCreate(['label' => 'Ε1'], ['alias' => 'E1', 'floor' => 5, 'car_lift' => 0, 'active' => true]);
        Apartment::firstOrCreate(['label' => 'ΣΤ1'], ['alias' => 'F1', 'floor' => 6, 'car_lift' => 1, 'active' => true]);
        Apartment::firstOrCreate(['label' => 'Ζ1'], ['alias' => 'G1', 'floor' => 7, 'car_lift' => 0, 'active' => true]);
        ExpenseCategory::firstOrCreate(['code' => 'cleaning'], ['name_el' => 'Καθαρισμός', 'name_en' => 'Cleaning', 'active' => true]);
        ExpenseCategory::firstOrCreate(['code' => 'service_contract'], ['name_el' => 'Σύμβαση εργασιών', 'name_en' => 'Service contract', 'active' => true]);
        ExpenseCategory::firstOrCreate(['code' => 'electricity'], ['name_el' => 'Κοινόχρηστο ρεύμα', 'name_en' => 'Common electricity', 'active' => true]);
        ExpenseCategory::firstOrCreate(['code' => 'water'], ['name_el' => 'Νερό', 'name_en' => 'Water', 'active' => true]);
        ExpenseCategory::firstOrCreate(['code' => 'insurance'], ['name_el' => 'Ασφάλεια πυρός', 'name_en' => 'Fire insurance', 'active' => true]);
        ExpenseCategory::firstOrCreate(['code' => 'gardening'], ['name_el' => 'Κήπος', 'name_en' => 'Gardening', 'active' => true]);
        ExpenseCategory::firstOrCreate(['code' => 'plumbing'], ['name_el' => 'Υδραυλικές εργασίες', 'name_en' => 'Plumbing', 'active' => true]);
        ExpenseCategory::firstOrCreate(['code' => 'passenger_lift_maintenance'], ['name_el' => 'Συντήρηση ανελκυστήρα', 'name_en' => 'Passenger lift maintenance', 'active' => true]);
        ExpenseCategory::firstOrCreate(['code' => 'passenger_lift_repairs'], ['name_el' => 'Επισκευές ανελκυστήρα', 'name_en' => 'Passenger lift repairs', 'active' => true]);
        ExpenseCategory::firstOrCreate(['code' => 'heating_maintenance'], ['name_el' => 'Συντήρηση θέρμανσης', 'name_en' => 'Heating maintenance', 'active' => true]);
        ExpenseCategory::firstOrCreate(['code' => 'heating_fuel'], ['name_el' => 'Πετρέλαιο θέρμανσης', 'name_en' => 'Heating fuel', 'active' => true]);
        ExpenseCategory::firstOrCreate(['code' => 'boiler_water'], ['name_el' => 'Boiler / νερό', 'name_en' => 'Boiler / water', 'active' => true]);
        ExpenseCategory::firstOrCreate(['code' => 'car_lift_electricity'], ['name_el' => 'Ρεύμα αναβατορίου αυτοκινήτων', 'name_en' => 'Car lift electricity', 'active' => true]);
        ExpenseCategory::firstOrCreate(['code' => 'car_lift_maintenance'], ['name_el' => 'Συντήρηση αναβατορίου αυτοκινήτων', 'name_en' => 'Car lift maintenance', 'active' => true]);
        ExpenseCategory::firstOrCreate(['code' => 'reserve'], ['name_el' => 'Αποθεματικό', 'name_en' => 'Reserve contribution', 'active' => true]);
    }
}
