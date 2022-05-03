# Cloud Storage
This EM will allow users to upload files from REDCap form/survey directly into your Google Storage bucket or Azure 
Datastore. The upload is done directly from user's browser to the cloud with no REDCap as middleware. 

### Azure Storage configuration:
1. Create an Azure account by going to https://azure.microsoft.com/
2. Get your storage account access key on the Azure console under Security + networking and enter it into your configuration.
3. In your Azure console navigate to Resource sharing (CORS). Under Blob service add a new CORS Rule to the storage container. 
Use the wildcard '*' for Allowed Origins, Allowed headers and Exposed headers. (**Note: File uploads don't work without the appropriate CORS rules**)
4. Create a new Blob Container by going to you azure console.

### Google Storage configuration:
1. Create your bucket using following link https://cloud.google.com/storage/docs/creating-buckets#storage-create-bucket-console
2. Create a Google Storage Service account that will access the bucket on behalf of your REDCap project. https://cloud.google.com/iam/docs/creating-managing-service-accounts
3. After creating service account a JSON file contains the account credentials will be downloaded on your machine. The EM needs the content of that file for configuration. 
4. Add the newly created service account into your bucket (**Note: if you want to access multiple buckets you need to repeat below steps for each bucket**). 

    a. Copy the value of client_email key in the service account JSON file. It will be something like '[SERVICE_ACCOUNT_NAME]@[PROJECT_ID].iam.gserviceaccount.com'. 
    
    b. In Google cloud console go to your newly created bucket. From left main menu click Storage -> Browser -> [YOUR_BUCKET_NAME]
    
    c. Click on Permission tab then click on add. In New Member box paste the client email you copied from the JSON file. 
    
    d. in role box search for storage admin. then click Save. 
5. Using `gutil` set below CORS settings for your bucket. https://cloud.google.com/storage/docs/configuring-cors#gsutil

`[
   {
     "origin": [
       "[YOUR_DOMAIN]"
     ],
     "responseHeader": [
       "Content-Type",
       "X-Requested-With",
       "Access-Control-Allow-Origin",
       "x-goog-resumable"
     ],
     "method": [
       "GET",
       "HEAD",
       "DELETE",
       "POST",
       "PUT",
       "OPTIONS"
     ],
     "maxAgeSeconds": 3600
   }
 ]`

#### REDCap EM configuration:
1. Click on External Modules in left Main Menu. then click configure for `GoogleStorage - v9.9.9`
2. from your Service Account JSON file copy the value of project_id and paste in `Google Storage Project ID`
3. Copy the json file content and save it into `Google Storage API Service Account`
4. Define the list of buckets name that you want to access via this EM.

![Alt text](assets/images/redcap-em-config.png?raw=true "REDCap EM Config" )

#### REDCap Form Configuration:
1. Create new text form field. 
2. In Action Tags/Field Annotation box add following `@GOOGLE-STORAGE=[YOUR_BUCKET_NAME]` or 
   `@AZURE-STORAGE=[YOUR_BLOB_CONTAINER]`

![Alt text](assets/images/redcap-field-config.png?raw=true "REDCap Field Config")

### Changelog

#### 03-05-2022
- Fixed uploads/downloads not working on surveys.

#### 28-04-2022
- Fixed public surveys.
- Fixed multi page surveys.
- Remove normal file upload link.