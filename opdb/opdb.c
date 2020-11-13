/*
 * Tarpeeksi Hyvae Soft 2016, 2019 /
 * opdb
 * 
 * Handles writing to and reading from an opdb database.
 * 
 */

#include <stdint.h>
#include <stdlib.h>
#include <string.h>
#include <stdio.h>
#include <float.h>
#include <time.h>

/* If the condition evaluates to true, produce the given user-facing error message,
 * then terminate the program.*/
static void bail_if(const int bailCondition, const char *const errMessage)
{
    if (bailCondition)
    {
        fprintf(stderr, "ERROR: %s\n", errMessage);
        exit(1);
    }

    return;
}

/* Append the given datum to the end of the given database file.*/
static void save_datum(const float value, const char *const filename)
{
    const int64_t secsSinceEpoch = (int64_t)time(NULL);
    FILE *const file = fopen(filename, "ab");

    bail_if((file == NULL), "Failed to open the database file.");

    /* Be a bit paranoid and double-check that we're dealing with correctly-
     * sized variables. This is basically a replacement for static_assert.*/
    bail_if((sizeof(secsSinceEpoch) != 8 || sizeof(value) != 4), "Unexpected data size.");

    /* Write out the data.*/
    bail_if(((fwrite((char*)&value, sizeof(value), 1, file) != 1) ||
             (fwrite((char*)&secsSinceEpoch, sizeof(secsSinceEpoch), 1, file) != 1)),
            "Failed to append the data to the database file.");

    fclose(file);
    return;
}

/* Print out all of the entries in the given database.*/
static void printout(const char *const filename)
{
    FILE *const file = fopen(filename, "rb");
    bail_if((file == NULL), "Failed to open the database file.");

    {
        unsigned i = 0;
        while (1)
        {
            int64_t secsSinceEpoch;
            float value;

            /* Be a bit paranoid and double-check that we're dealing with correctly-
             * sized variables. This is basically a replacement for static_assert.*/
            bail_if((sizeof(secsSinceEpoch) != 8 || sizeof(value) != 4), "Unexpected data size.");

            /* Load in the data.*/
            if ((fread((char*)&value, sizeof(value), 1, file) != 1) ||
                (fread((char*)&secsSinceEpoch, sizeof(secsSinceEpoch), 1, file) != 1))
            {
                /* Assume end of file.*/
                break;
            }

            /* Print out the data.*/
            {
                char *const dateString = asctime(localtime(&secsSinceEpoch));
                dateString[strlen(dateString) - 1] = '\0'; /* Remove newline.*/

                printf("%d. [%s] %f\n", ++i, dateString, value);
            }
        }
    }

    fclose(file);
    return;
}

/* Returns a numerical code corresponding to an action described by the given
 * string; such that e.g. "log" returns ACTION_LOG (as a hypothetical example).
 */
static enum { ACTION_LOG, ACTION_PRINTOUT, ACTION_NONE }
action_code(const char *const command)
{
    if (strcmp(command, "log") == 0) return ACTION_LOG;
    if (strcmp(command, "print") == 0) return ACTION_PRINTOUT;

    return ACTION_NONE;
}

/*
 * Expected command-line usage:
 * 
 *  opdb <command> <filename> [optional args]
 * 
 * - 'command' is the action you want performed wrt. the database.
 * - 'filename' is the name and path of the database's file to operate on.
 * - which optional arguments you'd pass depends on the command.
 * 
 */
int main(int argc, char **argv)
{
    bail_if((argc < 2),
            "Invalid number of arguments; expected at least a command and the name of a database file.");

    /* Execute the user-requested command.*/
    {
        const char *const command = argv[1];
        const char *const filename = argv[2];

        switch (action_code(command))
        {
            /* Append a new datum into the database file.*/
            case ACTION_LOG:
            {
                float value = 0;

                bail_if((argc != 4), "Unsupported number of arguments for 'log'.");

                /* Assume that the third argument gives as a string the floating-point
                 * value to log. Convert it from a string into a float.*/
                {
                    char *endPtr = NULL;
                    value = strtod(argv[3], &endPtr);

                    /* Verify that we got a good value out of the conversion.*/
                    bail_if((*endPtr != '\0'), "Failed to convert the given value into a float.");
                    bail_if((value != value), "The given value is not a number.");
                    bail_if((value < -DBL_MAX || value > DBL_MAX), "The given value is infinite.");
                }

                save_datum(value, filename);

                break;
            }

            /* Print out all of the database file's data.*/
            case ACTION_PRINTOUT:
            {
                bail_if((argc != 3), "Unsupported number of arguments for 'print'.");

                printout(filename);

                break;
            }

            default: bail_if(0, "Unknown command.");
        }
    }

    return 0;
}
