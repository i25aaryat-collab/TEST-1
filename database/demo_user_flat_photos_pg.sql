-- Everyday room photos for the 4 flats posted by demo users (photos from the team's earlier seed files, not used elsewhere).
UPDATE listings SET photo = 'https://dupgs.com/delhi-pg-single-room-study-desk.webp' WHERE id = 1 AND photo IS NULL AND title = 'Sunny room in a 2BHK near Vijay Nagar';
UPDATE listings SET photo = 'https://media.designcafe.com/wp-content/uploads/2020/06/03114726/dorm-room-ideas-for-your-home.jpg' WHERE id = 2 AND photo IS NULL AND title = 'Shared room, walk to Palasia market';
UPDATE listings SET photo = 'https://depanache.in/depanache-ui/uploads/2022/07/1-RK-interior-design-Ideas-6.jpg' WHERE id = 3 AND photo IS NULL AND title = 'Quiet room above a studio';
UPDATE listings SET photo = 'https://jumanji.livspace-cdn.com/magazine/wp-content/uploads/2019/01/29193318/interior-design-2-bhk-furnished_-living-2.jpg' WHERE id = 4 AND photo IS NULL AND title = 'Room in a 3BHK near DB Mall';
