FROM ubuntu
RUN apt-get update --fix-missing && apt-get install -y software-properties-common && add-apt-repository ppa:ondrej/php && apt-get update --fix-missing
RUN apt-get install -y php7.2-fpm && apt-get install -y php7.2-curl && apt-get install -y ffmpeg && apt-get install -y curl && mkdir temp && chmod -R 777 temp
RUN apt-get install -y bc && mkdir video && mkdir video/overlay && chmod -R 777 video
EXPOSE 9100
COPY src/* /home/
ENTRYPOINT ["php", "-S", "0.0.0.0:9100", "/home/listen.php"]


