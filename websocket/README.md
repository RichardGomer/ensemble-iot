Ensemble Websocket
==================

This is a websocket proxy for EnsembleIOT.

It allows commands to be sent and received via websocket and relayed via another broker. Multiple simultaneous clients are supported.

This feature can be run via docker, using the same docker image as the
main functionality. Simply override the command and pass through the
extra port, like so:

`
docker run -p 31075:31075 -p 8090:80 \
-e ENDPOINTURL=http://10.0.0.51:8090/ensemble-iot/1.0/ \
-e DEFAULTENDPOINTURL=http://10.0.0.8:3107/ensemble-iot/1.0/ \
ensemble-iot \
/home/pi/ensemble-iot/bin/runDockerWS.sh
`

`ENDPOINTURL` is the URL of the local endpoint (to announce to other
brokers) and `DEFAULTENDPOINTURL` is the default endpoint for routing
commands.

