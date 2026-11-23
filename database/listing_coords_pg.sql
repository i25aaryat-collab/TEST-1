-- v5: map coordinates for listings. Imported listings get the APPROXIMATE centre of their area
-- (loc_approx = 1, shown as a circle, never as an exact pin). New listings get an exact pin from the picker.
ALTER TABLE listings ADD COLUMN IF NOT EXISTS lat DOUBLE PRECISION, ADD COLUMN IF NOT EXISTS lng DOUBLE PRECISION,
  ADD COLUMN IF NOT EXISTS loc_approx SMALLINT NOT NULL DEFAULT 0;
UPDATE listings l SET lat = v.lat, lng = v.lng, loc_approx = 1 FROM (VALUES
 ('Allahabad','Civil Lines',25.4520,81.8340), ('Bengaluru','AECS Layout / Munnekolala',12.9600,77.7140), ('Bengaluru','Bhoganhalli',12.9310,77.7020),
 ('Bengaluru','Brookefield',12.9640,77.7180), ('Bengaluru','Channasandra / Kadugodi',12.9900,77.7590), ('Bengaluru','Devarabisanahalli / Bellandur',12.9400,77.6930),
 ('Bengaluru','Gantiganahalli',13.0700,77.5900), ('Bengaluru','Garudachar Palya',12.9890,77.7010), ('Bengaluru','Jayanagar',12.9250,77.5840),
 ('Bengaluru','Kadubeesanahalli',12.9370,77.6970), ('Bengaluru','Kadubeesanahalli / Panathur',12.9350,77.7000), ('Bengaluru','Kodbisanhalli',12.9380,77.6950),
 ('Bengaluru','Marathahalli',12.9570,77.7010), ('Bengaluru','Nagavara / Veerannapalya',13.0420,77.6180), ('Bengaluru','Panathur / Kadubeesanahalli',12.9330,77.7050),
 ('Bengaluru','Thubarahalli / Whitefield',12.9560,77.7240), ('Bengaluru','White Rose Layout, Whitefield',12.9700,77.7480), ('Bengaluru','Whitefield',12.9700,77.7500),
 ('Bhopal','Arera Colony',23.2110,77.4300), ('Chandigarh','Sector 35',30.7230,76.7600), ('Delhi','East of Kailash',28.5590,77.2440),
 ('Delhi','Jamia Nagar',28.5610,77.2800), ('Delhi','Kamla Nagar',28.6810,77.2050), ('Ghaziabad','Indirapuram',28.6460,77.3700),
 ('Gurugram','Sector 46',28.4340,77.0550), ('Indore','Bhawarkua',22.6920,75.8670), ('Indore','Jakhya',22.6600,75.8000),
 ('Indore','Palasia',22.7240,75.8840), ('Indore','Saraswati Nagar',22.7050,75.8450), ('Indore','Scheme No 54',22.7520,75.8960),
 ('Indore','Vijay Nagar',22.7530,75.8930), ('Jaipur','Gurjar Ki Thadi',26.8700,75.7700), ('Jaipur','Tonk Phatak',26.8800,75.8000),
 ('Jaipur','Tonk Road',26.8500,75.8050), ('Mumbai','Andheri Railway Station',19.1190,72.8470), ('Noida','Amity University area',28.5440,77.3330),
 ('Noida','Noida',28.5355,77.3910), ('Noida','Sector 104',28.5390,77.3700), ('Noida','Sector 63A Bahlolpur',28.6200,77.3850),
 ('Pune','Raghoba Patil Nagar',18.5100,73.9300), ('Pune','Rajiv Nagar South',18.5590,73.9100)
) AS v(city, area, lat, lng)
WHERE l.city = v.city AND l.area = v.area AND l.lat IS NULL;
