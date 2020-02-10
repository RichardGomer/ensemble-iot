Ensemble IoT
============

Ensemble IoT is a PHP framework for building IoT systems spread across multiple
devices.

Ensemble IoT aims to make communication between many logical devices , spread
across many physical devices, essentially network transparent. Each device
has a name, and devices can send messages to other devices that are then
actioned.

Ensemble IoT includes a simple Context Broker for working with distributed
sensor data, and some pre-built modules for applications that I have at home.

Inter-node communication is achieved over HTTP, although other transports should
be fairly straightforward to implement. (WebSockets are an obvious choice!)

Operating Principles
--------------------

* `devices` are essentially PHP objects. They can do things on a schedule, and/or
  wait to receive commands from elsewhere.
* `commands` are sent from one device to another. They are routed between devices
  either locally, within a commandbroker, or across devices using an inter-node
  transport.
* `endpoints` are transports that allow inter-node communication.
* Discovery of remote devices is done using a registration call to an endpoint,
  whereby one node tells another node about its local devices.
* If a node receives a command for a device it doesn't know about, it sends the
  command to a default endpoint - a bit like the default gateway in IP routing.

Running
-------

The main daemon operates as a command line PHP program.

The HTTP API needs to be served somehow - nginx works - and uses shared files to
pass data to the daemon.

The API and daemon both load (shared) configuration from files in config, named
with the IP address of the node. This way, a single repository can contain config
for all devices at once.

Testing
-------

We need a script that can spin up multiple daemons at once, as if they were
running on different hosts, and mocking the config/inter-node communication
as appropriate.
