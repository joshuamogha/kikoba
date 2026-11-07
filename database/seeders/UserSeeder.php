<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsGroupData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    use SeedsGroupData;

    /**
     * Seed a system administrator and the members of the sample groups.
     * Every account uses the password "password".
     */
    public function run(): void
    {
        $password = Hash::make('password');
        $prefixes = ['0712', '0713', '0754', '0755', '0765', '0767', '0784', '0689', '0622', '0743'];

        $users = [
            ['System Administrator', 'F', '1988-04-12', 'admin@kibubu.co.tz', '0754000001'],
            // Umoja wa Wanawake Kinondoni VICOBA
            ['Neema Mushi', 'F', '1979-02-18'],
            ['Asha Juma', 'F', '1984-07-03'],
            ['Mariam Hassan', 'F', '1981-11-25'],
            ['Grace Mwakalinga', 'F', '1990-05-09'],
            ['Fatuma Salim', 'F', '1976-09-30'],
            ['Rehema Kileo', 'F', '1987-01-14'],
            ['Zainab Mrisho', 'F', '1992-03-27'],
            ['Upendo Massawe', 'F', '1985-08-21'],
            ['Halima Bakari', 'F', '1978-12-05'],
            ['Esther Shayo', 'F', '1989-06-16'],
            ['Joyce Ndunguru', 'F', '1983-10-08'],
            ['Amina Said', 'F', '1995-04-02'],
            // Tumaini SACCOS Arusha
            ['Baraka Mollel', 'M', '1975-03-11'],
            ['Joseph Kimaro', 'M', '1980-08-19'],
            ['Happiness Urio', 'F', '1986-02-23'],
            ['Emmanuel Lyimo', 'M', '1978-06-07'],
            ['Daniel Mushi', 'M', '1982-10-29'],
            ['Peter Swai', 'M', '1974-01-15'],
            ['Elizabeth Mbise', 'F', '1988-09-12'],
            ['Godfrey Temba', 'M', '1981-05-26'],
            ['Rose Kessy', 'F', '1991-12-18'],
            ['Samson Laizer', 'M', '1993-07-31'],
        ];

        foreach ($users as $index => $user) {
            [$name, $gender, $dateOfBirth] = $user;

            $phone = $user[4] ?? $prefixes[$index % count($prefixes)].sprintf('%06d', ($index * 48271 + 123457) % 1000000);
            $email = $user[3] ?? Str::slug($name, '.').'@example.com';

            DB::table('users')->insert($this->stamp([
                'name' => $name,
                'phone_number' => $phone,
                'email' => $email,
                'password' => $password,
                'gender' => $gender === 'F' ? 'female' : 'male',
                'date_of_birth' => $dateOfBirth,
                'status' => 'active',
                'is_active' => true,
                'is_platform_admin' => $index === 0,
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ]));
        }
    }
}
