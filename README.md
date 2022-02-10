## relilab-cron-import
## Wordpress Plugin to import posts via WP REST API
### **_Note: This plugin requires ACF Pro in order to work_**

Zeile einfügen

geändert

**1. Installation**

This plugin can be download or routed directly to your WP Plugin page via link - verändert

***

**2. Importing Options**

In order to configure the plugin you need to import the option page and field groups via the Tool section of ACF Pro in your WP Backend
there are two JSON files in this repo which can be imported:

* **acf-export-2021-12-02.json**

Use the **Import Fieldgroup** function to import this JSON properly
This JSON are the fieldgroups used to save the information which is set by the option page

* **acfe-export-options-pages-relilab-cron-import-2021-12-02.json**

Use the **Import Options Pages** function to import this JSON propertly

***

**3. Option Page**

After importing both plugin and JSON files you should now see a new options page in your options section.
This Plugin gives you the following options to setup

* Anzahl der zu importierenden Beiträge pro URL

This number represents the amount of POSTs you want to import from all given websites
_Note: The higher this number is set the more time the import will require. 
A timeout of the Request might occure if set too high_

* Wordpress Homepage

This URL should be the homepage of the website from which you want the post imported **FROM**
_Note: The entered URL has to be a Wordpress website with standard WP REST calls enabled (this can be checked by accessing the site with postfix /wp-json/wp/v2/)_

* Kategorie Mapping

Here you can add custom mapping to map all the imported posts categories to specific existing categories
_Note: Use the given writing convention for the mapping to work seperate original and goal categorie by : and each entry with an enter (new line)_

* Standartkategorie

This will be the standard category which the plugin will revert to if the mapping can't be applied

* Kategorie erzeugen

Tick this in order to create a new category whenever the Mapping can't be applied

* Post Status

Here you can set the Post Status of the imported post to be able to make a last check before publishing the post

***

**4. Using the Plugin**

The plugin can be used either by shortcode or by setting up a Cronjob

* Shortcode

Shortcode name --> `relilab_import_cron`
* Cronjob

Action name --> `relilab_import_cron`

_Note: I recommend using the WP Crontrol Plugin which supplies a nice tool to manage cronjobs_

