FROM debian:buster-slim

# Expose HTTP
EXPOSE 80

# Create a user called pi, to be like a raspberry pi
RUN adduser --disabled-password --gecos "" pi

# Copy ensemble-iot into image and set permissions
WORKDIR /home/pi
COPY --chown=pi . /home/pi/ensemble-iot/

# Install ensemble-iot
RUN /home/pi/ensemble-iot/bin/install.sh

CMD /home/pi/ensemble-iot/bin/runDocker.sh
