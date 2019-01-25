#!/bin/bash
####################################################################
# A minimal test of opdb; tests whether the opdb logger can log and
# retrieve a value.
####################################################################

cd bin

TEST_VALUE=-123456789
TEST_FILE=`mktemp opdb_test_XXXXXXXXX.opdb`

./opdb log $TEST_FILE $TEST_VALUE
RESULT_VALUE=$(./opdb print $TEST_FILE | awk '{ print $NF }')

if (( $TEST_VALUE == $RESULT_VALUE )); then
    rm -f $TEST_FILE
    echo "Test succeeded."
    exit 0
fi

echo "TEST FAILED."
