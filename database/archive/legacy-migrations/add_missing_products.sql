SET FOREIGN_KEY_CHECKS=0;

-- Delete existing products and re-insert from latest
DELETE FROM market_products;

INSERT INTO `market_products` VALUES 
(1,1,10,'Kamatis (Tomatoes)','The tomato is a plant whose fruit is an edible berry that is eaten as a vegetable.','Vegetables',253.00,'kg',0,1,'https://upload.wikimedia.org/wikipedia/bcl/thumb/1/14/Kamatis_.jpg/1280px-Kamatis_.jpg','2025-11-14 10:45:01','2025-11-19 17:01:54'),
(2,2,11,'Sibuyas Pula (Red Onions)','Red onions are cultivars of the onion, and have purplish-red skin and white flesh tinged with red. They are most commonly used in cooking, but the skin has also been used as a dye.','Vegetables',239.00,'kg',0,1,'https://blog.plantwise.org/wp-content/uploads/sites/7/2023/02/wide-paul-magdas-SSIwIRCu7bM-unsplash-scaled.jpg','2025-11-16 13:19:06','2025-11-16 13:19:06'),
(3,1,10,'Sibuyas Pula (Red Onions)','Red onions are cultivars of the onion, and have purplish-red skin and white flesh tinged with red.','Vegetables',221.00,'kg',0,1,'https://png.pngtree.com/thumb_back/fh260/background/20231103/pngtree-textured-background-of-abundant-red-onions-captivating-photo-image_13717733.png','2025-11-17 07:25:37','2025-11-17 07:25:37');

SET FOREIGN_KEY_CHECKS=1;

