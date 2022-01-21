CREATE TABLE `ada` (
                       `adaid` int(11) NOT NULL,
                       `gesperrt` tinyint(1) NOT NULL DEFAULT '0',
                       `email` varchar(100) NOT NULL DEFAULT '',
                       `freigabe1u1` smallint(1) NOT NULL
) ENGINE=MyISAM;

ALTER TABLE `ada`
    ADD PRIMARY KEY (`adaid`);

ALTER TABLE `ada`
    MODIFY `adaid` int(11) NOT NULL AUTO_INCREMENT;