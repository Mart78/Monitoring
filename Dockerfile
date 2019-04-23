FROM peckadesign/php:7.2

RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

COPY ./docker/web/default.conf /etc/apache2/sites-enabled/000-default.conf
COPY . /var/www/html/

COPY ./config/docker.neon ./app/config/config.local.neon

RUN chmod -R 0777 temp/ log/
