-- Create development databases
CREATE DATABASE baselaravel12react_dev;
CREATE DATABASE baselaravel12react_staging;
CREATE DATABASE baselaravel12react_test;

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE baselaravel12react_dev TO laravel;
GRANT ALL PRIVILEGES ON DATABASE baselaravel12react_staging TO laravel;
GRANT ALL PRIVILEGES ON DATABASE baselaravel12react_test TO laravel;