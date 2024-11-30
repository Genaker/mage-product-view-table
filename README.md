# Installation
```
composer require mage/product-view
```
All the tables(ViewTable, MView, JSON) will be create on **setup:upgrade** </br>


Or you can run it with the comand **bin/magentopview:rum**. It will populate table with the data as well. 
```
bin/magento pview:run
```
Command Output:
<img width="619" alt="image" src="https://github.com/user-attachments/assets/7743836f-dd98-4618-806f-223a766558dd">

By running this code *populateProductJsonTableFromView($changeLog = false)* with the **true** parameter you are generateing data only for change log data 

Tables will be generated:
- **catalog_product_view** : it is view not a table. Contains dirrect SQL to the core magento tables. Update mechanizm **not** required however has performace of the raw queries 

<img width="436" alt="image" src="https://github.com/user-attachments/assets/31770dc4-3c81-4a3b-895f-205d62b2cf89">

- **catalog_product_view_MVIEW** : materialized view table from the view table. Update mechanizm is required

<img width="436" alt="image" src="https://github.com/user-attachments/assets/e0218e36-0870-476b-be57-a32a5faf461b">

- **product_json** : table with the denormilised json attribute data in the *data* field. Update mechanizm is also required

<img width="436" alt="image" src="https://github.com/user-attachments/assets/b751ebad-7022-456c-a095-5b0969385cda">
