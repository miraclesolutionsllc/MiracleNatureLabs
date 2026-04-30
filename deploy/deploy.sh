#!/bin/bash

# Deploy script for Miracle Nature Labs website to Namecheap hosting
# Usage: ./deploy.sh

echo "Starting deployment to Namecheap..."

scp -r -P 21098 \
    ../index.html \
    ../contact.php \
    ../privacy.html \
    ../terms.html \
    ../footer.html \
    ../about.html \
    ../header.html \
    ../contact.css \
    ../email-contact.css \
    ../style.css \
    ../legal.css \
    ../script.js \
    ../subscribe.php \
    ../assets/ \
    ../data/ \
    miranaou@162.0.232.35:/home/miranaou/miraclenaturelabs.com/

echo "Deployment complete!"
