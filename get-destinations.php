<?php
// get-destinations.php
include("db.php");
header('Content-Type: application/json');

$query = "SELECT * FROM destinations ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);

$destinations = [];
while ($row = mysqli_fetch_assoc($result)) {
    $destinations[] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'price' => $row['price'],
        'page' => !empty($row['page_link']) ? $row['page_link'] : '#',
        'description' => $row['description'],
        'image' => $row['image_url']
    ];
}

// If no destinations found, return static ones
if (empty($destinations)) {
    $destinations = [
        [
            'id' => 1,
            'name' => 'Lumbini',
            'price' => 'RS.21,480',
            'page' => 'lumbini.html',
            'description' => 'Birthplace of Lord Buddha, UNESCO World Heritage Site',
            'image' => 'images/lumbini.jpg'
        ],
        [
            'id' => 2,
            'name' => 'Chitwan National Park',
            'price' => 'RS.35,880',
            'page' => 'chitwan.html',
            'description' => 'Wildlife safari and jungle adventures',
            'image' => 'images/chitwan.jpg'
        ],
        [
            'id' => 3,
            'name' => 'Annapurna Base Camp (ABC)',
            'price' => 'RS.13,850',
            'page' => 'annapurna.html',
            'description' => 'Stunning mountain views and trekking paradise',
            'image' => 'images/arnapurna.jpg'
        ],
        [
            'id' => 4,
            'name' => 'Pokhara',
            'price' => 'RS.23,880',
            'page' => 'pokhara.html',
            'description' => 'Stunning mountain views and trekking paradise',
            'image' => 'images/pokhara.jpg'
        ]
    ];
}

echo json_encode($destinations);
?>