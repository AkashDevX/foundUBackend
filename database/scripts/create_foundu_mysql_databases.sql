-- Run once against MySQL as a user that can create databases (e.g. root).
-- Creates the master registry database and the three tenant databases.

CREATE DATABASE IF NOT EXISTS foundu_masterdb
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS bluegreenfacilityservicesdb
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS constructconceptsdb
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

CREATE DATABASE IF NOT EXISTS aidandableservicesdb
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
