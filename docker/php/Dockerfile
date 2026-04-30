FROM php:8.1-fpm

RUN apt-get update && apt-get install -y \
    freetds-dev \
    freetds-bin \
    unixodbc \
    unixodbc-dev \
    odbcinst \
    --no-install-recommends \
    && docker-php-ext-configure pdo_odbc --with-pdo-odbc=unixODBC,/usr \
    && docker-php-ext-install pdo_odbc \
    && rm -rf /var/lib/apt/lists/*

COPY freetds.conf /etc/freetds/freetds.conf
COPY odbc.ini     /etc/odbc.ini
COPY odbcinst.ini /etc/odbcinst.ini

WORKDIR /var/www/html
