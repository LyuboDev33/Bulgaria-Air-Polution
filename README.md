Clean Air Finder

Live demo: [https://YOUR-DOMAIN-HERE](https://snow-woodpecker-137125.hostingersite.com/)

Clean Air Finder is a Laravel web application that helps users compare historical air quality across selected Bulgarian cities and identify which city had the lowest average PM2.5 concentration over a chosen period.

Features

Select a historical date range.

Compare average PM2.5 concentrations across supported Bulgarian cities.

View a ranking of cities based on their average PM2.5 levels.

Retrieve results from a local database for fast filtering and comparison.

Data Source

Historical particulate matter data is sourced from Sensor.Community.

The application processes archived CSV files containing sensor measurements. Sensor coordinates are used to identify measurements within a 10 km radius of the supported cities.

The relevant PM2.5 measurements are aggregated by date and city, and the resulting values are stored in the database. When a user selects a date range, the application queries the stored data rather than downloading and processing historical files on every request.

