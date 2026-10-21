-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 20, 2026 at 06:06 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `tourist_planner`
--

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`category_id`, `category_name`, `description`) VALUES
(1, 'Nature', 'Natural attractions and scenic landscapes'),
(2, 'Religious', 'Places of worship and religious importance'),
(3, 'Cultural', 'Local culture and heritage attractions'),
(4, 'Sightseeing', 'Viewpoints and other sightseeing spots'),
(6, 'Accommodation', 'Accommodation information for visitors; no booking services');

--
-- Dumping data for table `places`
--

INSERT INTO `places` (`place_id`, `category_id`, `name`, `description`, `distance_km`, `opening_times`, `travel_tips`, `facilities`, `safety_information`, `visitor_suitability`, `latitude`, `longitude`, `image_path`) VALUES
(2, 6, 'Loinorn North Cove Cabana', 'Northcove Cabanas is a rural accommodation property in Loinorn Estate, Bogawantalawa, surrounded by tea-country scenery. Its secluded setting appeals to visitors looking for a quiet stay close to nature.', 9.20, 'Day-visitor hours are not confirmed. Contact the property before arrival and confirm whether day visits are accepted.', 'Confirm directions and the current condition of the estate approach road. Ask whether your vehicle is suitable and arrange arrival during daylight.', 'Accommodation, parking and toilets are locally reported. Previous guests mention prepared meals; confirm food arrangements and whether ingredients must be supplied.', 'Take care on wet estate roads and outdoor paths. Confirm access after heavy rain, supervise children near water and follow management guidance.', 'Visitors comfortable with a remote rural setting. Families and visitors with limited mobility should confirm vehicle access, steps and bathroom facilities before travelling.', 6.7868028, 80.7322631, 'uploads/places/e737c240f9e164c4c560713c5f23bc8d.jpg'),
(3, 1, 'Forest Waterfall', 'This waterfall is located within the forest in the local area. Visitors reach it on foot after travelling part of the way by vehicle, making it a nature stop for those comfortable with forest walking.', 2.30, 'No formal opening hours are confirmed. Visit during daylight and confirm local access permission.', 'Vehicle access covers only part of the journey. Follow a known path with someone familiar with the route, and carry drinking water.', 'No designated parking, public toilets or food outlets are reported at the waterfall.', 'Paths and rocks can be slippery. Avoid visits during heavy rain, keep away from the waterfall edge and do not assume the water is safe for swimming.', 'Best suited to visitors comfortable walking on uneven forest paths. The route may be difficult for young children and people with limited mobility.', 6.7690268, 80.6713020, 'uploads/places/1a7e1debd5107dba00140b90f927b04b.png'),
(4, 1, 'Chair-Shaped Large Stone', 'A large natural rock formation resembling a chair stands near the forest in the local area. Reaching it involves a walk, offering visitors a chance to observe an unusual natural feature and its surroundings.', 2.50, 'No formal opening hours are confirmed. Daylight visits are recommended; confirm local access permission.', 'Vehicles can cover part of the route, followed by walking. Ask someone familiar with the area for directions and wear suitable walking shoes.', 'No designated parking, public toilets or food outlets are reported at the site. Carry water and take all litter away.', 'The approach can be slippery, particularly during rain. Avoid visiting in heavy rain and do not climb wet or unstable rock surfaces.', 'Best suited to visitors comfortable walking on uneven ground. Access may be difficult for young children and people with limited mobility.', 6.7683944, 80.6725132, 'uploads/places/548f0ec6cf0a5284c7414de375ef477c.jpg'),
(5, 3, 'Bogawana Tea Factory', 'Bogawana Tea Factory is a local tea-production site associated with the surrounding tea-estate landscape. It is included as a point of interest for visitors learning about the area’s tea industry. Public entry and guided visits must be confirmed with management.', 7.60, 'Public visiting hours are not confirmed. Contact estate or factory management before visiting.', 'Day visits are possible according to local information. Confirm visiting times with staff and obtain permission before entering production areas.', 'Visitor parking, toilets, refreshments and guided tours are not confirmed. Ask management about available facilities.', 'Follow staff instructions and stay clear of machinery, loading areas and factory vehicles. Wear closed footwear if a visit is permitted.', 'Visitors interested in tea production, subject to permission. Confirm age restrictions, walking requirements and accessibility before bringing children or visitors needing assistance.', 6.7982124, 80.6841332, 'uploads/places/4f915f65d36beed0e020fc6b6b11bffd.png'),
(6, 4, 'Local Tea Plantations Kotiyagala', 'Kotiyagala is a working tea-estate landscape in the Bogawantalawa Valley. Its cultivated hillsides provide views of the area’s tea-growing surroundings, making it a scenic stop for visitors interested in local plantation life.', 6.70, 'No public visiting hours are confirmed. View during daylight from permitted areas; obtain permission before entering estate land.', 'The area can be approached by vehicle or on foot. Stay on permitted roads and paths, and avoid interrupting estate work.', 'No designated visitor parking or public toilets are reported at this viewing spot. Park only where permitted without blocking estate traffic.', 'Sloping paths may become slippery in rain. Avoid steep or muddy sections and remain clear of working vehicles.', 'Suitable for scenic viewing from accessible permitted areas. Walking through the slopes may be difficult for young children and visitors with limited mobility.', 6.7975379, 80.6757370, 'uploads/places/7d0fba4078facd63aef8b7192bbb8d91.jpg'),
(7, 2, 'Our Lady of La Salette Church', 'Our Lady of La Salette Church is a small community church in the local area. It provides a peaceful place for Christian worship, prayer and reflection.', 0.25, '6:00 AM–6:00 PM, based on local information. Confirm access during special services.', 'Accessible by vehicle or on foot. Park in the available area and approach the church respectfully.', 'Parking is available according to local information. Toilets and other visitor facilities are not confirmed.', 'Paths can be slippery, especially during rain. Walk carefully and assist anyone who has difficulty with uneven surfaces.', 'Families and older visitors may visit, depending on their mobility. Confirm steps and access arrangements if assistance is needed.', 6.7695461, 80.6861670, 'uploads/places/716f9fa9b4ff6d379c4508710988f4a7.png'),
(8, 2, 'Christ Church Warleigh, Dickoya', 'Christ Church Warleigh is a historic church in Dickoya, set among tea-country scenery near Castlereagh Reservoir. Visitors can appreciate its stone architecture and peaceful surroundings while respecting its role as an active place of worship.', 21.60, 'Confirm current visiting hours with the church or caretaker before travelling. Access may vary during services.', 'Plan your visit during daylight. Dress respectfully, keep noise low and ask the caretaker before entering or taking photographs.', 'Visitor parking, public toilets and step-free access have not been confirmed. Check with the caretaker before your visit.', 'Take care on wet paths and steps, supervise children and follow notices and caretaker guidance.', 'Suitable for visitors interested in religious heritage and quiet surroundings. Visitors with limited mobility should confirm access arrangements beforehand.', 6.8634170, 80.5952467, 'uploads/places/2f020d3e91f607a0fb75b8aa47966993.png'),
(9, 2, 'Holy Rosary Church, Bogawantalawa', 'Holy Rosary Church is a Catholic place of worship in Bogawantalawa. It serves the local religious community and offers visitors an opportunity for quiet prayer and respectful observation of parish life.', 7.00, 'Visiting hours are not confirmed. Contact the parish for opening hours and service times.', 'Plan your visit around parish activities. Dress respectfully and avoid disturbing services or private ceremonies.', 'Parking, toilet access and step-free entry for visitors have not been confirmed.', 'Take care on wet surfaces and around vehicles. Follow parish notices and keep children supervised.', 'Visitors interested in worship and local religious culture. Confirm access arrangements for visitors with limited mobility.', 6.7991688, 80.6717479, 'uploads/places/8a43a51e3d0041733a264a810b4790e9.png'),
(10, 2, 'Holy Cross Church Hatton', 'Holy Cross Church is a Catholic place of worship in Hatton. It provides a setting for prayer and religious services and is a point of interest for visitors exploring the area’s religious community.', 24.00, 'Visiting hours are not confirmed. Check with the parish before visiting.', 'Confirm the church entrance and parking arrangements in advance. Respect ongoing services and ask permission before photographing the interior.', 'Visitor parking, toilets and accessible entry are not confirmed. Please enquire with the parish.', 'Be careful around nearby traffic and on wet steps or pathways. Follow instructions during services and busy periods.', 'Visitors interested in prayer and religious places. Those needing step-free access or assistance should confirm arrangements beforehand.', 6.8950582, 80.5967828, 'uploads/places/858638a2a549debeb7f162ce92aa31a4.png'),
(11, 2, 'Infant Jesus Church Battalgalle', 'Infant Jesus Church in Battalgalla is a Catholic place of worship within the Diocese of Kandy. It serves the local community through prayer, religious services and parish activities.', 21.70, 'Visiting hours are not confirmed. Contact the parish for current opening hours and Mass times.', 'Confirm the entrance and visiting arrangements with the parish. Dress respectfully, keep noise low and ask permission before taking photographs.', 'Visitor parking, toilet access and step-free entry have not been confirmed. Check with the parish before travelling.', 'Follow notices and parish guidance. Take care on wet surfaces and supervise children near roads and vehicle areas.', 'Visitors interested in worship and local religious life. Confirm steps, seating and accessible entry for anyone requiring mobility support.', 6.8682157, 80.6186258, 'uploads/places/bce7d127cefd81d9e81d021cc634db97.png'),
(12, 1, 'BopathThalawa Mountain Campsite', 'Bopaththalawa, also known as Haritha Kanda, is a grassy hill-country hiking and camping area near Bogawantalawa. Its open slopes and surrounding mountain scenery attract visitors interested in walking, photography and outdoor experiences.', 11.00, 'Visitor access hours are not confirmed. Plan daytime visits and confirm permission before hiking or camping.', 'Vehicles can reach part of the approach, followed by walking. Confirm the current route, vehicle suitability and access requirements locally before setting out.', 'Designated parking, toilets, drinking water and food outlets are not confirmed. Arrange parking locally and carry your own supplies.', 'Trails can become slippery in rain. Avoid hiking during heavy rain, thunderstorms or poor visibility. Wear suitable footwear and travel with someone familiar with the route.', 'Best suited to visitors comfortable walking on uneven terrain. The walking section may be difficult for young children and people with limited mobility; vehicle access does not cover the full route.', 6.8094044, 80.6958807, 'uploads/places/49cff95bba509f4d221b5b52fefaabe4.jpg'),
(13, 6, 'Ceylon Tea Trails - Norwood Bungalow', 'Norwood Bungalow is a heritage-style accommodation property within Ceylon Tea Trails in Sri Lanka’s hill country. Its tea-estate surroundings, gardens and scenic views offer a peaceful setting for visitors interested in the region’s plantation landscape.', 21.00, 'Guest access is subject to a confirmed reservation. Contact the property for check-in and check-out times.', 'Arrange your stay directly with the property before travelling. Access without an accommodation reservation should be confirmed in advance.', 'Guest accommodation, dining, gardens, lounge and swimming pool. Parking and toilets are locally reported; confirm availability for non-resident visitors.', 'Take care on wet outdoor paths and supervise children near the swimming pool. Ask the property about road conditions during heavy rain.', 'Visitors seeking a quiet tea-country setting. Families and visitors with limited mobility should confirm suitable access and facilities before visiting.', 6.8346740, 80.6042662, 'uploads/places/5eb9d276f36f2006d5a839c6da422c90.jpg');

--
-- Dumping data for table `place_images`
--

INSERT INTO `place_images` (`image_id`, `place_id`, `image_path`, `sort_order`) VALUES
(9, 2, 'uploads/places/40ca547093bdac12b872b0df828a179e.jpg', 1),
(10, 2, 'uploads/places/e7709b5762ff36e2312ed600201224ea.jpg', 2),
(12, 2, 'uploads/places/5cc89d443091cb05ca19a50bc480b6a6.jpg', 3),
(13, 3, 'uploads/places/d5d7abe9f2015e2876d00caa7765cc47.jpg', 1),
(14, 3, 'uploads/places/1e62d738d9df6c85d31b5f7276163ca8.jpg', 2),
(15, 3, 'uploads/places/7df35fe6dddf29df82abaa6f94587ddc.png', 3),
(16, 5, 'uploads/places/5a890c857d69b7eb1f6a58cdd982e655.jpg', 1),
(17, 5, 'uploads/places/65ba989120e40ac0865ad35747c0b5f7.jpg', 2),
(18, 5, 'uploads/places/dd82283b9f77b4919ad6a757c96f7847.png', 3),
(19, 6, 'uploads/places/5813e5b1f660d8990b16df481980b425.jpg', 1),
(20, 6, 'uploads/places/0f5bf93cb971b281e0e30530db08ddf3.jpg', 2),
(21, 6, 'uploads/places/fce29380989738f53f27078f5aab8de8.jpg', 3),
(22, 7, 'uploads/places/23303b72ecfa881bd6c9b38c897ccdda.jpg', 1),
(23, 7, 'uploads/places/736dead58280580094334600562924c4.png', 2),
(24, 7, 'uploads/places/a56b3086147ea76f39823a4b2fd6e458.jpg', 3),
(26, 8, 'uploads/places/0e6c3290e874ab059cff6ee41a5c4604.jpg', 1),
(27, 8, 'uploads/places/fffc9062d5f6d3e29607a749ad8d02d2.jpg', 2),
(28, 8, 'uploads/places/844af63470f252b9f6e01276a19c6119.jpg', 3),
(29, 9, 'uploads/places/ec98d896d01464e3174572c7a2a905c0.jpg', 1),
(30, 9, 'uploads/places/c69444c0f2fd346c4f93ed3613af675d.jpg', 2),
(31, 9, 'uploads/places/6824702aa66c1df289c8b8400cb1096a.png', 3),
(32, 10, 'uploads/places/db75470a56eda301d03c26440db5cd61.jpg', 1),
(33, 10, 'uploads/places/0e1763cf3be8367c1708e2e7a573921f.jpg', 2),
(34, 10, 'uploads/places/34f2d29baa371d4f2ab5b0bb2f31fabc.png', 3),
(35, 11, 'uploads/places/6d74428d02ff472fca6bcddd709f1be8.jpg', 1),
(36, 11, 'uploads/places/0368382dc46a1de33594b33a80d176a2.jpg', 2),
(37, 11, 'uploads/places/dc4281698d314d74a5b4f12541911b36.jpg', 3),
(38, 12, 'uploads/places/c9ac586214612c9cf4d6faed913cf723.jpg', 1),
(39, 12, 'uploads/places/1cbcf7051b9e7cd2628a0799755209f4.jpg', 2),
(40, 12, 'uploads/places/c458e04de52db092d125510311309f88.jpg', 3),
(41, 13, 'uploads/places/d897d63eae386e12d02624dd7cec5998.jpg', 1),
(42, 13, 'uploads/places/8784b9d6f8a22109abd928e5f56c3750.jpg', 2),
(43, 13, 'uploads/places/f75a683e4cad56962690ff14bd382bec.jpg', 3);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
