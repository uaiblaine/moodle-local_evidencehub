# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## Unreleased

### Added

- Stage S1: the data model (seven tables), five seeded evidence types, seven capabilities, English
  and Brazilian Portuguese strings, and a privacy provider covering every table with personal data.
- The export request keeps a count of its workers instead of the proposal's list of ids: the
  dossier rows of the export already say who it covered, and a list inside a text column could not
  be cleaned by a deletion request.
