FROM ensemble-iot-env:latest

# Expose HTTP
EXPOSE 80

USER pi

# Copy ensemble-iot into image and set permissions
WORKDIR /home/pi
COPY --chown=pi . /home/pi/ensemble-iot/

CMD /home/pi/ensemble-iot/bin/runDocker.sh
