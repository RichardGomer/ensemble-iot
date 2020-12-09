#!/usr/bin/env python3
"""Poll a MiFlora sensor continuously"""

import argparse
import logging
import re
import sys
import time
import math
import os
from os.path import dirname, realpath

sys.path.append(dirname(realpath(__file__)) + '/miflora')

from btlewrap import BluepyBackend, GatttoolBackend, PygattBackend, available_backends

from miflora import miflora_scanner
from miflora.miflora_poller import (
    MI_BATTERY,
    MI_CONDUCTIVITY,
    MI_LIGHT,
    MI_MOISTURE,
    MI_TEMPERATURE,
    MiFloraPoller,
)

def valid_miflora_mac(
    mac, pat=re.compile(r"C4:7C:8D:[0-9A-F]{2}:[0-9A-F]{2}:[0-9A-F]{2}")
):
    """Check for valid mac adresses."""
    if not pat.match(mac.upper()):
        raise argparse.ArgumentTypeError(
            f'The MAC address "{mac}" seems to be in the wrong format'
        )
    return mac


def _get_backend(args):
    """Extract the backend class from the command line arguments."""
    if args.backend == "gatttool":
        backend = GatttoolBackend
    elif args.backend == "bluepy":
        backend = BluepyBackend
    elif args.backend == "pygatt":
        backend = PygattBackend
    else:
        raise Exception(f"unknown backend: {args.backend}")
    return backend


def main():
    """Main function"""
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--backend", choices=["gatttool", "bluepy", "pygatt"], default="gatttool"
    )

    parser.add_argument("mac", type=valid_miflora_mac)

    args = parser.parse_args()

    """Poll data from the sensor."""
    backend = _get_backend(args)
    poller = MiFloraPoller(args.mac, backend)

    while(True):
        current_time = math.floor(time.time());

        print("{} temperature {}".format(current_time, poller.parameter_value(MI_TEMPERATURE, False)))
        print("{} moisture {}".format(current_time, poller.parameter_value(MI_MOISTURE, False)))
        print("{} light {}".format(current_time, poller.parameter_value(MI_LIGHT, False)))
        print("{} conductivity {}".format(current_time, poller.parameter_value(MI_CONDUCTIVITY, False)))
        print("{} battery {}".format(current_time, poller.parameter_value(MI_BATTERY, False)))

        time.sleep(15)


if __name__ == "__main__":
    main()
