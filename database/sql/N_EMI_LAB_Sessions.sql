CREATE TABLE N_EMI_LAB_Sessions (
    id VARCHAR(255) NOT NULL,
    user_id VARCHAR(50) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(MAX) NULL,
    payload VARCHAR(MAX) NOT NULL,
    last_activity INT NOT NULL,
    CONSTRAINT PK_N_EMI_LAB_Sessions PRIMARY KEY (id)
);

CREATE INDEX IX_N_EMI_LAB_Sessions_last_activity ON N_EMI_LAB_Sessions (last_activity);

CREATE INDEX IX_N_EMI_LAB_Sessions_user_id ON N_EMI_LAB_Sessions (user_id);
