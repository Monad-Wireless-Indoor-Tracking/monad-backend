# Introduction
We are working on a mobile app, that will be collecting data about BLE advertisements. User will be prompted to do some action insode of a room - walk from A to B, wait there about 10s, walk back, ... This will generate many data, since we will be collecting data from arount 10 BLE advertisers at the same time. 

# Feature specification
We are planning to store these data in a cloud - specifically S3 bucket. We will assigne user a unique URL that he will be able to upload files to. Since this might be a lot's of data - we want to upload directly to S3 bucket. We need a solution to file uploadig.

## Experiments
We experimented with creating a signed URL that would be valid for 15m. This URL would then be used to upload files to a given repository. These experiments are located in this directory.

I need you to investigate the corectness of this approach - provide possible implementation on backend - how could user upload files based on presigned URLs by our backend and how to maintain security. 

Create  a report of how good is this approach, what could be done better, what are the options, security risks,...