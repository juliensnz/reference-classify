# Akeneo reference entity classifier
Automatically classify Akeneo records based on it's images

![Demo](https://user-images.githubusercontent.com/1117272/58373410-222b5880-7f2e-11e9-8e53-5789ca9a40c3.gif)

# Installation

    git clone https://github.com/juliensnz/reference-classify.git
    composer install

Create a text attribute (non locale specific and non channel specific) on the reference entity you want to classify.

Then duplicate the `.env` file to `.env.local` and fill the required informations:

    #Akeneo credential created in Akeneo PIM > System > Api Connections
    AKENEO_API_BASE_URI=
    AKENEO_API_CLIENT_ID=
    AKENEO_API_CLIENT_SECRET=
    AKENEO_API_USERNAME=admin
    AKENEO_API_PASSWORD=admin

    #The cache file for labels
    CACHE_FILENAME=./var/cache/labels.json

    #The text attribute code you want to put your tags in
    TAG_ATTRIBUTE=raw_tags

    #Amazon API credentials created here https://console.aws.amazon.com/iam/home#/users
    AMAZON_KEY=
    AMAZON_SECRET=

# Usage

    bin/console app:classify your_reference_entit_code
    bin/console app:classify your_reference_entit_code --tagAttribute=my_tag_attribugte_code
    bin/console app:classify your_reference_entit_code --confidenceThreshold=70
