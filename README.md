# Thunderbird to Gmail library

This library helps you copying Thunderbird filters to Gmail.

## Introduction

Thunderbird saves email filters in a file named `msgFilterRules.dat`.
This library can read this file, and create automatically the required folders and filters in a Gmail account.

## Requirements

### Locate the `msgFilterRules.dat` file

You need to know where Thunderbird saves the `msgFilterRules.dat` file.
In order to do that, open Thunderbird and:

1. show the menu (use the `ALT-F` keys)
2. under the `Tools` menu, choose `Account Settings`
3. in the left list, choose the `Server Settings` item under the account name
4. in the right pane, you'll see the `Message Storage` section: the folder containing the file `msgFilterRules.dat` should be the one specified in the `Local Directory` field

### Create a Gmail Service account

You need a Gmail service account in order to use this library.
Here's the complete list of steps required to correctly create and configure it:

1. create a Google project
   1. go to the [Google Cloud Platform](https://console.cloud.google.com/) dashboard page
   2. [create a new project](https://console.cloud.google.com/projectcreate)
2. enable the Gmail API
   1. go to the [Google Cloud Platform](https://console.cloud.google.com/) dashboard page
   2. from the menu, choose *APIs & Services* &rarr; *Library*
   3. search for *Gmail API* and enable them for the project created above
4. create a Service Account
   1. go to the [Google Cloud Platform](https://console.cloud.google.com/) dashboard page
   2. from the menu, choose *IAM & Admin* &rarr; *Service Accounts*
   3. click the *+ Create service account* button
      1. in the Step 1, enter any name/description you like
      2. in the Step 2, choose the *Owner* Role (*Full access to all resources.*)
      3. in the Step 3, you can leave the default (empty) values
   4. in the Service Account list, choose the *Create key* action for the newly created service account
      1. download the key in *JSON* format
      2. save the `.json` file in a secure location
   3.  in the Service Account list, choose the *Edit* action for the newly created service account
      1. in the *Service account status* section, click the *Show domain-wide delegation*
      2. check the *Enable G Suite Domain-wide Delegation* option
      3. save
4. grant the required OAuth scopes
   1. go to the [Google Admin](https://admin.google.com/) page
   2. from the menu, choose *Security* &rarr; *API Controls*
   3. in the *Domain wide delegation* section, click the *Manage domain wide delegation* link
   4. add a new API client
      1. in the *Client ID* field enter the number associated to the `client_id` key in the `.json` file you downloaded above
      2. you have to specify these *OAuth scopes* ([here](https://developers.google.com/identity/protocols/oauth2/scopes) you can find all the available scopes):
         - `https://www.googleapis.com/auth/gmail.labels`
           (required to manage the folders of the Gmail accounts)
         - `https://www.googleapis.com/auth/gmail.settings.basic`
           (required to manage the filters of the Gmail accounts)

## Sample usage

Let's assume that:

- you downloaded the JSON key for the service account to `path/to/key.json`
- the Thunderbird filters files is available at `path/to/msgFilterRules.dat`
- the email address of the Gmail account is `me@my.domain`

With the above data, you can copy the Thunderbird filters to Gmail with a few lines of code like these:

```php
// Parse the Thunderbird filters
$filters = \TBF2GM\MsgFilterRulesDatParser::fromFile('path/to/key.json')->parse();

// Initialize the Google API Client
$client = new \Google\Client();
$client->setAuthConfig('path/to/key.json');
$client->setScopes([\Google_Service_Gmail::GMAIL_LABELS, \Google_Service_Gmail::GMAIL_SETTINGS_BASIC]);
$client->setSubject('me@my.domain');

// Create the Gmail filters
$writer = new \TBF2GM\Gmail\FilterWriter($client);
$problems = $writer->ensureFilters($filters);

// Let's print any error that may occur
foreach ($problems as $problemIndex => $problem) {
    echo 'Problem ', $problemIndex + 1, (string) $problem, "\n";
}
```

Ad this point, you should have the Thunderbird filters available in Gmail.


## Known issues

- It seems it's not possible to create Gmail filters with rules like `begins with`, `ends with`, `is exactly`, ...
  Gmail filters always search for *parts* of the text.
  So, for example, a Tunderbird criteria like `If the subject starts with "Some text"` will generate a Gmail filter like `If the subject contains "Some text"`.
- The "Stop Filter Execution" Tunderbird action is ignored (it seems there's no way to implement such feature in Gmail filters)
- It seems it's not possible to create Gmail filters with header-specific criterias
- Not every criteria/action has been implemented in this library.
  We implemented only the ones we actually needed.

## How to delete all the Gmail filters and/or folders

If you want to test this library, you may need to delete all the existing Gmail filters and/or folders.
You can do that with this code (we assume that the `$client` variable is initialized as described in the *Sample usage* section above)

```php
$service = new \Google_Service_Gmail($client);

// Delete all the existing Gmail filters
foreach ($service->users_settings_filters->listUsersSettingsFilters('me')->getFilter() as $filter) {
    $service->users_settings_filters->delete('me', $filter->getId());
}

// Delete all the existing Gmail folders
foreach ($service->users_labels->listUsersLabels('me') as $label) {
    if ($label->getType() === 'user') {
        $service->users_labels->delete('me', $label->getId());
    }
}
```
