# Installation
```
composer require mage/product-view
```
All the tables(ViewTable, MView, JSON) will be create on **setup:upgrade** </br>


You can also run it with the command **bin/magento pview:run**, which will populate the table with the data. 
```
bin/magento pview:run
```
Command Output: </br>
<img width="619" alt="image" src="https://github.com/user-attachments/assets/7743836f-dd98-4618-806f-223a766558dd">

By running this code *populateProductJsonTableFromView($changeLog = false)* with the **true** parameter, you are generating data only for change log data 

Tables will be generated:
- **catalog_product_view** : it is a view, not a table. Runs direct SQL to the core magento tables. Update mechanism **not** required however, has performace of the raw queries 

<img width="436" alt="image" src="https://github.com/user-attachments/assets/31770dc4-3c81-4a3b-895f-205d62b2cf89">

- **catalog_product_view_MVIEW** : materialized view table from the view table. An update mechanism is required

<img width="436" alt="image" src="https://github.com/user-attachments/assets/e0218e36-0870-476b-be57-a32a5faf461b">

- **product_json** : table with the denormilised json attribute data in the *data* field. An update mechanism is also required

<img width="436" alt="image" src="https://github.com/user-attachments/assets/b751ebad-7022-456c-a095-5b0969385cda">
