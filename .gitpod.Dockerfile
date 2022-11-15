FROM gitpod/workspace-base:latest

COPY --from=composer/composer:2-bin /composer /usr/bin/composer
COPY --from=ghcr.io/friendsofshopware/shopware-cli /usr/local/bin/shopware-cli /usr/bin/shopware-cli

RUN sudo add-apt-repository ppa:ondrej/php -y && \
    curl -fsSL https://deb.nodesource.com/setup_16.x | sudo bash - && \
    wget https://get.symfony.com/cli/installer -O - | sudo bash -s -- --install-dir=/usr/bin && \
    sudo apt-get install -y \
    php8.1-fpm php8.1-mysql php8.1-curl php8.1-gd php8.1-xml php8.1-zip php8.1-opcache php8.1-mbstring php8.1-intl php8.1-cli \
    rsync \
    mysql-client-8.0 \
    nodejs && \
    shopware-cli completion bash | sudo tee /etc/bash_completion.d/shopware-cli && \
    shopware-cli completion zsh | sudo tee /usr/local/share/zsh/site-functions/_shopware-cli && \
    shopware-cli completion fish | sudo tee /usr/share/fish/completions/shopware-cli.fish && \
    sudo apt-get upgrade -y && \
    echo "memory_limit=512M" > php.ini && \
    echo "assert.active=0" >> php.ini && \
    echo "opcache.interned_strings_buffer=20" >> php.ini && \
    echo "zend.detect_unicode=0" >> php.ini && \
    echo "realpath_cache_ttl=3600" >> php.ini && \
    sudo cp php.ini /etc/php/8.1/cli/conf.d/99-overrides.ini && \
    sudo cp php.ini /etc/php/8.1/fpm/conf.d/99-overrides.ini && \
    rm php.ini && \
    echo "[client]" > ~/.my.cnf && \
    echo "host=127.0.0.1" >> ~/.my.cnf && \
    echo "user=root" >> ~/.my.cnf && \
    echo "password=root" >> ~/.my.cnf
