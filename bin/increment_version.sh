#!/bin/bash

## Temporary script to increment semantic versioning.
# We should switch to something like npm semantic-release
# in the future.
# (see: https://www.npmjs.com/package/semantic-release)
increment_version() {
    local version="$1"
    local part="$2"

    # Remove the 'v' prefix for easier manipulation
    version="${version#v}"

    # Split the version into an array
    IFS='.' read -r major minor hotfix <<< "$version"

    case "$part" in
        major)
            ((major++))
            minor=0
            hotfix=0
            ;;
        minor)
            ((minor++))
            hotfix=0
            ;;
        hotfix)
            ((hotfix++))
            ;;
        *)
            echo "Invalid part specified. Use 'major', 'minor', or 'hotfix'."
            exit 1
            ;;
    esac

    # Construct the new version string
    echo "v$major.$minor.$hotfix"
}

# Check if the correct number of arguments is provided
if [ "$#" -ne 2 ]; then
    echo "Usage: $0 <version> <part>"
    echo "Example: $0 v1.0.0 minor"
    exit 1
fi

increment_version "$1" "$2"
