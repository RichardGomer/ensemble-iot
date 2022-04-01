
#!/bin/bash

echo "Testing irrigation control"

for valve in 0 1 2 3 # 23 21 26 # 1 2 3 4 h1 h2 h3
do
	echo "Test valve $valve"

	# Open a valve
	gpio mode $valve out
	gpio write $valve 0
	echo "Valve ON"
	sleep 0.2

	# Test the pump
	echo "Pumping"
	echo "YOU MUST STOP PUMPING WITH CTRL-C"
	python3 softstart.py 40 100 # physpin intensity%
	echo "Pumping complete"
	sleep 0.2

	# Close the valve
	gpio write $valve 1
	echo "Valve OFF"
	sleep 0.2
done

echo "Tests complete"
