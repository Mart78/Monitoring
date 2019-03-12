FROM peckadesign/php:7.1

RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

COPY . /var/www/html/

COPY ./config/docker.neon ./app/config/config.local.neon

RUN chmod -R 0777 temp/ log/

