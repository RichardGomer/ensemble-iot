#include <stdio.h>
#include <stdlib.h>
#include <wiringPi.h>

int sensorPin;

volatile int pulseCount;
unsigned long oldTime;

void pulseCounter()
{
  pulseCount++;
}

void setup(int argc, char *argv[])
{
  wiringPiSetupGpio();

  if(argc < 2) {
	printf("USAGE: ./flowfreq bcm_sense_pin\n");
	exit(0);
  }

  // Broadcom pin number must be passed as first argument
  char* p = NULL;
  sensorPin = strtol(argv[1], &p, 10);

  printf("Freq monitor attached to BCM pin %i\n", sensorPin);
  printf("Format is period:pulses - period is measured in milliseconds, and is usually about 1 second\n");

  pinMode(sensorPin, INPUT);
  digitalWrite(sensorPin, HIGH);

  pulseCount        = 0;
  oldTime           = millis();

  wiringPiISR(sensorPin, INT_EDGE_FALLING, &pulseCounter);

}

void loop()
{
  int now = millis();

  if((now - oldTime) >= 1000)    // Only process counters once per second
  {
    int time = (now - oldTime);

    printf("%u:%u\n", time, pulseCount);

    oldTime = now;

    pulseCount = 0;
  }
}

int main(int argc, char *argv[])
{
  setbuf(stdout,NULL);

  setup(argc, argv);

  while(1) {
    loop();
    delay(100);
  }
}
