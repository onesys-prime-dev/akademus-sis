<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class RoomsSeeder extends Seeder
{
    public function run()
    {
        $db = $this->db;

        $rooms = [
            ['name' => 'Lab A',          'capacity' => 40, 'type' => 'Computer Laboratory', 'floor' => '2nd'],
            ['name' => 'Lab B',          'capacity' => 40, 'type' => 'Computer Laboratory', 'floor' => '2nd'],
            ['name' => 'Networking Lab', 'capacity' => 30, 'type' => 'Laboratory',          'floor' => '3rd'],
            ['name' => 'Room 305',       'capacity' => 50, 'type' => 'Lecture',             'floor' => '3rd'],
            ['name' => 'Room 405',       'capacity' => 45, 'type' => 'Lecture',             'floor' => '4th'],
        ];

        foreach ($rooms as $room) {
            if ($db->table('rooms')->where('name', $room['name'])->countAllResults() === 0) {
                $room['is_active']  = 1;
                $room['created_at'] = date('Y-m-d H:i:s');
                $room['updated_at'] = date('Y-m-d H:i:s');
                $db->table('rooms')->insert($room);
                echo "Created room {$room['name']} ({$room['floor']} floor)\n";
            }
        }
    }
}
